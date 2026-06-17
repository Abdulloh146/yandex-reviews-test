const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { chromium } = require('playwright');

const MAX_REVIEWS  = Number(process.env.YANDEX_MAX_REVIEWS   || 700);
const MAX_SCROLLS  = Number(process.env.YANDEX_MAX_SCROLLS   || 3500);
const SCROLL_WAIT  = Number(process.env.YANDEX_SCROLL_WAIT_MS || 900);
const MAX_RUNTIME  = Number(process.env.YANDEX_MAX_RUNTIME_MS || 900000);
const REQUIRE_ALL  = process.env.YANDEX_REQUIRE_ALL_REVIEWS === '1';
const DEBUG        = process.env.YANDEX_DEBUG === '1';

const tempDir = path.join(process.cwd(), 'storage', 'app', 'playwright-temp');
fs.mkdirSync(tempDir, { recursive: true });
process.env.TEMP = process.env.TMP = process.env.TMPDIR = tempDir;

// ─── Date regex (all Yandex formats) ────────────────────────────────────────
const DATE_RE = /(?:\d{1,2}\s*-?\s*(?:январ[яь]?|феврал[яь]?|март[а]?|апрел[яь]?|ма[яй]|июн[яь]?|июл[яь]?|август[а]?|сентябр[яь]?|октябр[яь]?|ноябр[яь]?|декабр[яь]?|апр|авг|сент|окт|нояб|дек|янв|февр|yanvar|fevral|mart|aprel|may|iyun|iyul|avgust|sentabr|oktabr|noyabr|dekabr)\.?\s*(?:\d{4})?|\d{1,2}\s+(?:минут[аы]?|час[аов]?|дн[ейя]|недел[иь]|месяц(?:а|ев)?|лет|год[ау]?)\s+назад|(?:сегодня|вчера|неделю|месяц|год|дня|дней|час|часа|часов|минут|минуты|минута)\s+назад)/i;

// ─── Helpers ─────────────────────────────────────────────────────────────────
function clean(t) {
    return String(t||'').replace(/\u00A0/g,' ').replace(/[ \t]+/g,' ').replace(/\n\s+/g,'\n').trim();
}
function oneLine(t) { return clean(t).replace(/\s+/g,' ').trim(); }
function makeHash(t) { return crypto.createHash('sha1').update(String(t||''),'utf8').digest('hex'); }
function normalizeNumber(v) {
    const n = parseInt(String(v||'').replace(/[^\d]/g,''),10);
    return (Number.isFinite(n)&&n>0&&n<2000000)?n:0;
}

function normalizeDateText(value) {
    if (!value) return null;
    if (typeof value === 'number') {
        const ms = value > 1e12 ? value : value*1000;
        const d = new Date(ms);
        return isNaN(d.getTime()) ? null : d.toISOString().slice(0,10);
    }
    const t = oneLine(String(value));
    const iso = t.match(/\d{4}-\d{2}-\d{2}/);
    return iso ? iso[0] : (t||null);
}

function dateToIso(raw) {
    if (!raw) return null;
    const t = oneLine(String(raw)).toLowerCase();
    const isoM = t.match(/(\d{4})-(\d{2})-(\d{2})/);
    if (isoM) return isoM[0];
    const months = {
        'января':1,'январ':1,'янв':1,'yanvar':1,
        'февраля':2,'феврал':2,'февр':2,'fevral':2,
        'марта':3,'март':3,'mart':3,
        'апреля':4,'апрел':4,'апр':4,'aprel':4,
        'мая':5,'май':5,'may':5,
        'июня':6,'июн':6,'iyun':6,
        'июля':7,'июл':7,'iyul':7,
        'августа':8,'август':8,'авг':8,'avgust':8,
        'сентября':9,'сентябр':9,'сент':9,'sentabr':9,
        'октября':10,'октябр':10,'окт':10,'oktabr':10,
        'ноября':11,'ноябр':11,'нояб':11,'noyabr':11,
        'декабря':12,'декабр':12,'дек':12,'dekabr':12,
    };
    const m = t.match(/(\d{1,2})\s*-?\s*([а-яёa-z]+)\.?\s*(\d{4})?/i);
    if (!m) return null;
    const day=Number(m[1]), month=months[m[2]]||null, year=Number(m[3]||new Date().getFullYear());
    if (!day||!month||!year) return null;
    return `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
}

function parseRating(raw) {
    if (raw===null||raw===undefined) return null;
    if (typeof raw==='number') {
        if (raw>=1&&raw<=5) return Math.round(raw);
        if (raw>=10&&raw<=50) return Math.round(raw/10);
    }
    const t = String(raw||'');
    const m1 = t.match(/([1-5])\s*(?:из\s*5|\/\s*5)/i); if (m1) return Number(m1[1]);
    const m2 = t.match(/([1-5])(?:[.,]0)?\s*(?:звезд|stars|yulduz)/i); if (m2) return Number(m2[1]);
    const stars = t.match(/★{1,5}/); if (stars) return stars[0].length;
    const direct = t.match(/^([1-5])(?:[.,]\d)?$/); if (direct) return Math.round(parseFloat(direct[1].replace(',','.')));
    return null;
}

function addToMap(map, review) {
    if (!review||!review.text||review.text.length<3||!review.author_name) return false;
    const key = makeHash([review.author_name,review.review_date_text||'',review.text,review.rating||''].join('|'));
    if (map.has(key)) return false;
    map.set(key,{...review,external_id:review.external_id||`yandex-${key}`,content_hash:review.content_hash||key});
    return true;
}

// ─── Network collector ────────────────────────────────────────────────────────
function normalizeReviewObj(obj) {
    if (!obj||typeof obj!=='object') return null;
    const possText = obj.text??obj.reviewText??obj.comment??obj.description??obj.body??obj.message??obj.content??null;
    let text='';
    if (typeof possText==='string') text=clean(possText);
    else if (possText&&typeof possText==='object') text=clean(possText.text||possText.value||possText.content||'');
    if (!text||text.length<3||text.length>10000) return null;

    const authorObj=obj.author||obj.user||obj.profile||obj.account||{};
    const authorName=obj.authorName??obj.userName??obj.name??authorObj.name??authorObj.displayName??authorObj.fullName??authorObj.title??null;
    if (!authorName||String(authorName).length<2||String(authorName).length>160) return null;

    const dateRaw=obj.date??obj.createdAt??obj.created??obj.time??obj.timestamp??obj.publishedAt??obj.updatedAt??obj.publicationDate??obj.reviewDate??null;
    const dateText=normalizeDateText(dateRaw);
    const rating=parseRating(obj.rating)??parseRating(obj.rate)??parseRating(obj.stars)??parseRating(obj.score)??parseRating(obj.grade)??parseRating(obj.ratingValue)??null;
    const contentHash=makeHash([oneLine(String(authorName)),dateText||'',text,rating||''].join('|'));
    return {
        external_id:String(obj.id||obj.reviewId||obj.uri||`yandex-${contentHash}`),
        author_name:oneLine(String(authorName)),
        review_date:dateToIso(dateText),
        review_date_text:dateText,
        text,rating,content_hash:contentHash,
    };
}

function collectFromJson(value,bag=[]) {
    if (!value) return bag;
    if (Array.isArray(value)) { for (const i of value) collectFromJson(i,bag); return bag; }
    if (typeof value!=='object') return bag;
    const direct=normalizeReviewObj(value);
    if (direct) { bag.push(direct); return bag; }
    for (const [k,child] of Object.entries(value)) {
        const kl=k.toLowerCase();
        if (kl.includes('review')||kl.includes('feedback')||kl.includes('comment')||kl.includes('rating')||kl.includes('sharh')||kl.includes('fikr')||kl.includes('author')||kl.includes('user')||Array.isArray(child)||(child&&typeof child==='object'))
            collectFromJson(child,bag);
    }
    return bag;
}

function setupNetworkCollector(page, networkMap) {
    page.on('response', async (res) => {
        try {
            const type = res.request().resourceType();
            if (!['xhr','fetch'].includes(type)) return;
            if (res.status()<200||res.status()>=300) return;
            const url = res.url();
            if (!/yandex\.(ru|uz|com|by|kz|tr)/i.test(url)) return;
            let text; try { text=await res.text(); } catch(_) { return; }
            if (!text||text.length<20) return;
            if (!/review|отзыв|sharh|fikr|author|rating|comment/i.test(text.slice(0,5000))) return;
            let json; try { json=JSON.parse(text); } catch(_) {
                const m=text.match(/[a-zA-Z_$][\w$]*\s*\(\s*([\s\S]*)\s*\)\s*;?\s*$/);
                if (m) try { json=JSON.parse(m[1]); } catch(_) { return; } else return;
            }
            if (!json) return;
            const bag=[]; collectFromJson(json,bag);
            let added=0;
            for (const r of bag) {
                const key=makeHash([r.author_name||'',r.review_date_text||'',r.text||'',r.rating||''].join('|'));
                if (!networkMap.has(key)) { networkMap.set(key,{...r,external_id:r.external_id||`yandex-${key}`,content_hash:r.content_hash||key}); added++; }
            }
            if (added>0) console.error(`[net] +${added} from ${url.slice(0,80)}, total:${networkMap.size}`);
        } catch(_) {}
    });
}

// ─── DOM review extraction ────────────────────────────────────────────────────
function isOrgReply(text) {
    // Filter out organization replies (Ответ организации / Официальный ответ)
    return /^(?:ответ\s+организации|официальный\s+ответ|ответ\s+на\s+отзыв)/i.test(oneLine(text).slice(0,60));
}

function isOnlyUserLevel(text) {
    const v = oneLine(text).toLowerCase();
    return /^знаток города \d+/i.test(v)||/^городской знаток \d+/i.test(v)||/^city expert level \d+/i.test(v)||/^\d+\s*-?\s*darajadagi/i.test(v);
}

function parseReviewFromRaw(raw, cardRating=null) {
    const normalizedRaw = clean(raw);
    if (normalizedRaw.length<10) return null;

    // Skip org replies entirely
    if (isOrgReply(normalizedRaw)) return null;

    let lines = normalizedRaw.split('\n').map(l=>oneLine(l)).filter(Boolean);
    if (lines.length<2) return null;

    let authorName = lines[0];
    if (authorName.length>120||authorName.split(' ').length>10) return null;
    if (!authorName||authorName.length<2) return null;

    const trashAuthor = /^(обзор|фото|адрес|контакты|время работы|особенности|новости|товары|услуги|маршрут|рейтинг|отзывы|добавить|написать отзыв|яндекс|yandex|карты|карта|панорама|ответ организации|официальный ответ|подписаться|по умолчанию|похожие места|особенности места)$/i;
    if (trashAuthor.test(authorName)) return null;
    // Author name must not start with "Официальный ответ..."
    if (/^официальный\s+ответ/i.test(authorName)||/^ответ\s+организации/i.test(authorName)) return null;

    const reviewDate = lines.find(l=>DATE_RE.test(l))||null;

    lines = lines.filter(line => {
        const l=line.toLowerCase();
        if (line===authorName) return false;
        if (reviewDate&&line===reviewDate) return false;
        if (/ответ организации|официальный ответ/i.test(l)) return false;
        if (l.includes('знаток города')||l.includes('городской знаток')||l.includes('city expert')||l.includes('shahar bilimdoni')) return false;
        if (l.includes('подписаться')) return false;
        if (/^\d+$/.test(l)) return false;
        if (/^[★☆]+$/.test(line)) return false;
        if (isOnlyUserLevel(line)) return false;
        if (/^(?:Оценка|Rating|Baho)\s*[:：]?\s*[1-5]/i.test(line)) return false;
        return true;
    });

    const text = lines.join('\n').trim();
    if (!text||text.length<2||isOnlyUserLevel(text)) return null;
    if (/яндекс|yandex|пользовательское соглашение|служба поддержки|маршрут|адрес|телефон|график работы|написать отзыв|добавить фото/i.test(text)) return null;

    const rating = parseRating(normalizedRaw)||cardRating;
    const contentHash = makeHash([authorName,reviewDate||'',text,rating||''].join('|'));
    return {
        external_id:`yandex-${contentHash}`,
        author_name:authorName,
        review_date:dateToIso(reviewDate),
        review_date_text:reviewDate,
        text,rating,content_hash:contentHash,
    };
}

async function extractFromDom(page) {
    const rawItems = await page.evaluate(() => {
        function clean(v){return String(v||'').replace(/\u00A0/g,' ').replace(/[ \t]+/g,' ').replace(/\n\s+/g,'\n').trim();}
        function hasDate(t){
            return /(?:\d{1,2}\s*-?\s*(?:январ|феврал|март|апрел|ма[йя]|июн|июл|август|сентябр|октябр|ноябр|декабр|апр|авг|сент|окт|нояб|дек|янв|февр|yanvar|fevral|mart|aprel|may|iyun|iyul|avgust|sentabr|oktabr|noyabr|dekabr)\.?\s*(?:\d{4})?|\d{1,2}\s+(?:минут[аы]?|час[аов]?|дн[ейя]|недел[иь]|месяц(?:а|ев)?|лет|год[ау]?)\s+назад|(?:сегодня|вчера|неделю|месяц|год|дня|дней|час|часа|часов|минут|минуты|минута)\s+назад)/i.test(t);
        }
        function isOrgReply(t){return /^(?:ответ\s+организации|официальный\s+ответ)/i.test(t.slice(0,50));}
        function getRating(el){
            const vals=[];
            for(const n of el.querySelectorAll('*')){
                for(const a of ['aria-label','title','alt','data-rating']){const v=n.getAttribute(a);if(v)vals.push(v);}
                const t=n.textContent||'';
                if(/оценка|рейтинг|rating|звезд|star|yulduz|★/i.test(t))vals.push(t);
            }
            for(const v of vals){const m=String(v).replace(',','.').match(/([1-5](?:\.\d)?)/);if(m){const r=parseFloat(m[1]);if(r>=1&&r<=5)return r;}}
            const s=(el.innerText||'').match(/★{1,5}/);return s?s[0].length:null;
        }
        function looksLike(t){
            const v=clean(t);
            if(v.length<8||v.length>10000)return false;
            if(isOrgReply(v))return false;
            const lines=v.split('\n').map(l=>clean(l)).filter(Boolean);
            if(lines.length<2||lines.length>150)return false;
            const first=lines[0]||'';
            if(first.length<2||first.length>130)return false;
            if(/^(обзор|фото|адрес|маршрут|рейтинг|отзывы|яндекс|yandex|карты|особенности|похожие|подписаться|по умолчанию|ответ организации|официальный ответ)$/i.test(first))return false;
            if(/яндекс|yandex|пользовательское соглашение/i.test(v))return false;
            return hasDate(v);
        }
        function isTooBig(el){const r=el.getBoundingClientRect();return r.height>5000||r.width>1400;}
        function hasReviewChild(el){
            for(const c of el.children){if(looksLike(clean(c.innerText||c.textContent||''))&&!isTooBig(c))return true;}
            return false;
        }

        const candidates=[], seen=new Set();

        // Strategy 1: known class selectors
        const sels=['[class*="business-review-view"]','[class*="business-review"]','[data-testid*="review"]','[class*="review-snippet"]','[class*="review-card"]','[class*="reviews-list"] > *'];
        for(const sel of sels){
            for(const node of document.querySelectorAll(sel)){
                if(seen.has(node)||isTooBig(node))continue;
                const t=clean(node.innerText||node.textContent||'');
                if(!looksLike(t))continue;
                if(hasReviewChild(node))continue;
                seen.add(node);
                candidates.push({raw:t,cardRating:getRating(node),top:node.getBoundingClientRect().top+window.scrollY});
            }
        }

        // Strategy 2: scan all divs/articles/li
        if(candidates.length===0){
            for(const node of document.querySelectorAll('div,article,li,section')){
                if(seen.has(node)||isTooBig(node))continue;
                const rect=node.getBoundingClientRect();
                if(rect.width<150||rect.height<40)continue;
                const t=clean(node.innerText||node.textContent||'');
                if(!looksLike(t))continue;
                if(hasReviewChild(node))continue;
                const lines=t.split('\n').map(l=>clean(l)).filter(Boolean);
                const wc=(lines[0]||'').split(/\s+/).length;
                if(wc<1||wc>8)continue;
                seen.add(node);
                candidates.push({raw:t,cardRating:getRating(node),top:rect.top+window.scrollY});
            }
        }

        // Deduplicate
        const seen2=new Set(),deduped=[];
        for(const c of candidates.sort((a,b)=>a.top-b.top)){
            const key=c.raw.slice(0,80);
            if(!seen2.has(key)){seen2.add(key);deduped.push(c);}
        }
        return deduped.slice(0,5000);
    }).catch(()=>[]);

    const reviews=[];
    for(const item of rawItems){
        const r=parseReviewFromRaw(item.raw,item.cardRating);
        if(r) reviews.push(r);
    }
    return reviews;
}

// ─── Scroll ───────────────────────────────────────────────────────────────────
async function findScrollContainer(page) {
    await page.evaluate(() => {
        // Find the LEFT PANEL scroll container (Yandex Maps sidebar)
        // It's typically: width 300-700px, left side, overflow:scroll/auto, lots of scrollable content
        function isScrollable(el) {
            if (!el||el===document.body||el===document.documentElement) return false;
            const s=window.getComputedStyle(el);
            return ['auto','scroll','overlay'].includes(s.overflowY)&&el.scrollHeight>el.clientHeight+100;
        }
        function getScrollParent(node) {
            let el=node?.parentElement,d=0;
            while(el&&d<30){if(isScrollable(el))return el;el=el.parentElement;d++;}
            return null;
        }
        function score(el) {
            if (!isScrollable(el)) return -Infinity;
            const rect=el.getBoundingClientRect();
            if (rect.width<150||rect.height<100) return -Infinity;
            if (rect.width>900) return -Infinity; // not the full-page container
            const cls=String(el.className||'').toLowerCase();
            if (/map|ymaps|canvas/i.test(cls)) return -Infinity; // not map container
            let sc=el.scrollHeight-el.clientHeight; // scrollable amount
            const t=(el.innerText||el.textContent||'').slice(0,3000);
            if(hasReviewText(t)) sc+=200000;
            if(rect.left<100) sc+=50000; // left panel preferred
            if(rect.width<700) sc+=30000; // sidebar width
            const cls2=String(el.className||'').toLowerCase();
            if(/review|card|list|panel|sidebar|result|org/i.test(cls2)) sc+=50000;
            return sc;
        }
        function hasReviewText(t) {
            return /(?:\d{1,2}\s*(?:январ|феврал|март|апрел|ма[йя]|июн|июл|август|сентябр|октябр|ноябр|декабр|апр|авг|сент))|(?:назад)|(?:отзыв)/i.test(t);
        }

        let best=null,bestScore=-Infinity;

        // First try: walk up from review elements
        const reviewSels=['[class*="business-review-view"]','[class*="business-review"]','[data-testid*="review"]','[class*="review-card"]'];
        for(const sel of reviewSels){
            const nodes=document.querySelectorAll(sel);
            if(!nodes.length)continue;
            for(const node of [nodes[0],nodes[Math.floor(nodes.length/2)],nodes[nodes.length-1]]){
                const sp=getScrollParent(node);
                if(!sp)continue;
                const sc=score(sp);
                if(sc>bestScore){bestScore=sc;best=sp;}
            }
        }

        // Fallback: scan all scrollable elements
        if(!best){
            for(const el of document.querySelectorAll('*')){
                const sc=score(el);
                if(sc>bestScore){bestScore=sc;best=el;}
            }
        }

        window.__sc=best||null;
        if(best){
            const r=best.getBoundingClientRect();
            console.log('[sc] found: w='+Math.round(r.width)+' h='+Math.round(r.height)+' scrollable='+(best.scrollHeight-best.clientHeight)+' cls='+String(best.className).slice(0,50));
        } else {
            console.log('[sc] NOT FOUND - fallback to window');
        }
    }).catch(()=>{});
}

async function doScroll(page, amount) {
    const scrolled = await page.evaluate((amount) => {
        let el=window.__sc;
        // Validate
        if(el){try{if(!document.contains(el)||el.scrollHeight<=el.clientHeight+50){el=null;window.__sc=null;}}catch(_){el=null;}}
        // Re-find from review nodes
        if(!el){
            function isScrollable(e){if(!e||e===document.body||e===document.documentElement)return false;const s=window.getComputedStyle(e);return['auto','scroll','overlay'].includes(s.overflowY)&&e.scrollHeight>e.clientHeight+100;}
            function getScrollParent(node){let p=node?.parentElement,d=0;while(p&&d<30){if(isScrollable(p))return p;p=p.parentElement;d++;}return null;}
            const sels=['[class*="business-review-view"]','[class*="business-review"]','[data-testid*="review"]','article','li'];
            for(const sel of sels){
                const nodes=document.querySelectorAll(sel);
                if(!nodes.length)continue;
                const sp=getScrollParent(nodes[0]);
                if(sp&&sp.scrollHeight>sp.clientHeight+100){el=sp;window.__sc=sp;break;}
            }
        }
        if(el){
            const before=el.scrollTop;
            el.scrollTop+=amount;
            // Also dispatch wheel event for lazy-load triggers
            el.dispatchEvent(new WheelEvent('wheel',{bubbles:true,cancelable:true,deltaY:amount,deltaMode:0}));
            // Scroll last review into view
            const revs=el.querySelectorAll('[class*="business-review-view"],[class*="business-review"],[data-testid*="review"]');
            if(revs.length)try{revs[revs.length-1].scrollIntoView({behavior:'instant',block:'end'});}catch(_){}
            return {found:true,scrolled:el.scrollTop-before,el:'custom'};
        }
        // window fallback
        window.scrollBy(0,amount);
        document.documentElement.scrollTop+=amount;
        document.body.scrollTop+=amount;
        return {found:false,scrolled:0,el:'window'};
    }, amount).catch(()=>({found:false,scrolled:0}));

    // Always do mouse wheel too (triggers lazy load)
    await page.mouse.wheel(0, amount).catch(()=>{});
    // Keyboard end
    await page.keyboard.press('End').catch(()=>{});
    await page.waitForTimeout(SCROLL_WAIT);
    return scrolled;
}

async function resetScroll(page) {
    await page.evaluate(()=>{const el=window.__sc;if(el)try{el.scrollTop=0;}catch(_){}window.scrollTo(0,0);}).catch(()=>{});
    await page.waitForTimeout(1000);
}

// ─── UI helpers ───────────────────────────────────────────────────────────────
async function acceptPopups(page) {
    const texts=['Принять','Понятно','Хорошо','OK','ОК','Разрешить','Продолжить','Accept','Agree','Qabul qilish','Tushunarli'];
    for(const text of texts){
        try{
            const items=page.getByText(text,{exact:false});
            const count=await items.count();
            for(let i=0;i<Math.min(count,5);i++){
                const item=items.nth(i);
                if(await item.isVisible({timeout:200}).catch(()=>false))
                    await item.click({timeout:600,force:true}).catch(()=>{});
            }
        }catch(_){}
    }
}

async function clickMoreButtons(page) {
    const texts=['Показать ещё','Показать еще','Ещё','Еще','Читать полностью','Развернуть','Показать полностью','Batafsil',"Ko'proq",'Подробнее'];
    for(const text of texts){
        try{
            const items=page.getByText(text,{exact:false});
            const count=await items.count();
            for(let i=0;i<Math.min(count,100);i++){
                const item=items.nth(i);
                if(await item.isVisible({timeout:100}).catch(()=>false)){
                    await item.click({timeout:600,force:true}).catch(()=>{});
                    await page.waitForTimeout(80);
                }
            }
        }catch(_){}
    }
}

async function clickReviewsTab(page) {
    for(let attempt=0;attempt<10;attempt++){
        const clicked=await page.evaluate(()=>{
            function isVisible(el){const r=el.getBoundingClientRect(),s=window.getComputedStyle(el);return r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none';}
            function fireClick(el){try{el.scrollIntoView({block:'center'});}catch(_){}const r=el.getBoundingClientRect();const o={bubbles:true,cancelable:true,view:window,clientX:r.left+r.width/2,clientY:r.top+r.height/2};el.dispatchEvent(new MouseEvent('mouseover',o));el.dispatchEvent(new MouseEvent('mousedown',o));el.dispatchEvent(new MouseEvent('mouseup',o));el.dispatchEvent(new MouseEvent('click',o));}
            const all=Array.from(document.querySelectorAll('button,a,[role="tab"],[role="button"],span,div'));
            const cands=[];
            for(const el of all){
                if(!isVisible(el))continue;
                const text=(el.innerText||el.textContent||'').replace(/\s+/g,' ').trim();
                if(!text||text.length>90)continue;
                if(/^(Отзывы|Отзывов|Fikrlar|Sharhlar)(\s+\d{1,6})?$/i.test(text)||/^(\d{1,6})\s*(отзывов|отзыва|отзыв|ta\s*sharh)$/i.test(text)){
                    const r=el.getBoundingClientRect();
                    cands.push({el,text,top:r.top,hasNum:/\d/.test(text)?1000:0});
                }
            }
            cands.sort((a,b)=>(b.hasNum-a.hasNum)||(a.top-b.top));
            if(!cands.length)return false;
            fireClick(cands[0].el);
            return true;
        }).catch(()=>false);
        if(clicked){await page.waitForTimeout(3500);await acceptPopups(page);return true;}
        await page.mouse.wheel(0,600).catch(()=>{});
        await page.waitForTimeout(900);
    }
    return false;
}

async function detectBot(page) {
    const t=await page.locator('body').innerText().catch(()=>'');
    return /captcha|робот|robot|проверка|verify|подтвердите/i.test(t);
}

async function saveDebug(page,name) {
    if(!DEBUG)return;
    const dir=path.join(process.cwd(),'storage','app','parser-debug');
    fs.mkdirSync(dir,{recursive:true});
    try{await page.screenshot({path:path.join(dir,`${name}.png`),fullPage:false});}catch(_){}
    try{fs.writeFileSync(path.join(dir,`${name}.html`),await page.content(),'utf8');}catch(_){}
}

// ─── Business data ────────────────────────────────────────────────────────────
async function extractBusiness(page) {
    return await page.evaluate(()=>{
        function clean(v){return String(v||'').replace(/\u00A0/g,' ').replace(/\s+/g,' ').trim();}
        function normalizeNumber(v){const n=parseInt(String(v||'').replace(/[^\d]/g,''),10);return(Number.isFinite(n)&&n>0&&n<2000000)?n:0;}
        function parseRatingLine(t){const v=clean(t);const m=v.match(/([1-5][,.]\d)\s+([\d\s]+)\s*(?:оценок|оценки|оценка|ta\s*baho|baho)/i);return m?{rating:parseFloat(m[1].replace(',','.')),ratingsCount:normalizeNumber(m[2])}:null;}

        const rawText=clean(document.body.innerText||'');
        const lines=rawText.split('\n').map(clean).filter(Boolean);

        let name=null;
        for(const el of document.querySelectorAll('h1,h2,[class*="card-title"],[class*="business-card-title"],[class*="orgpage-header"]')){
            const t=clean(el.innerText||el.textContent||'');
            if(t.length>=2&&t.length<=120&&!/^(обзор|фото|отзывы|карта|маршрут)$/i.test(t)){name=t;break;}
        }

        let rating=null,ratingsCount=0,reviewsCount=0;
        for(let i=0;i<lines.length;i++){
            const line=lines[i],next=lines[i+1]||'';
            const rd=parseRatingLine(line);
            if(rd){rating=rating??rd.rating;ratingsCount=ratingsCount||rd.ratingsCount;}
            const m1=line.match(/(?:Отзывы|Отзывов|Sharhlar)\s+(\d{1,6})/i);
            if(m1){reviewsCount=reviewsCount||normalizeNumber(m1[1]);continue;}
            if(/^(?:Отзывы|Отзывов|Sharhlar)$/i.test(line)&&/^\d{1,6}$/.test(next)){reviewsCount=reviewsCount||normalizeNumber(next);continue;}
            const m2=line.match(/^(\d{1,6})\s*(?:отзывов|отзыва|отзыв|ta\s*sharh)/i);
            if(m2)reviewsCount=reviewsCount||normalizeNumber(m2[1]);
        }
        return{name,averageRating:rating,ratingsCount,reviewsCount};
    }).catch(()=>({name:null,averageRating:null,ratingsCount:0,reviewsCount:0}));
}

// ─── Main collection loop ─────────────────────────────────────────────────────
async function collectAllReviews(page, maxReviews, startedAt, networkMap) {
    const all=new Map();
    let lastCount=0, staleLoops=0, lastNewAt=0;

    await findScrollContainer(page);
    await resetScroll(page);
    await page.waitForTimeout(2000);

    for(let i=0;i<MAX_SCROLLS;i++){
        if(Date.now()-startedAt>=MAX_RUNTIME){console.error(`[loop] max runtime at ${i}`);break;}

        if(i%4===0) await clickMoreButtons(page);

        const domRevs=await extractFromDom(page);
        for(const r of domRevs) if(addToMap(all,r)) lastNewAt=i;
        for(const r of networkMap.values()) if(addToMap(all,r)) lastNewAt=i;

        const current=all.size;
        if(i%10===0) console.error(`[${i}/${MAX_SCROLLS}] reviews:${current}/${maxReviews} net:${networkMap.size} stale:${staleLoops}`);
        if(maxReviews>0&&current>=maxReviews){console.error(`[loop] target reached ${current}`);break;}

        if(current===lastCount) staleLoops++;
        else staleLoops=0;
        lastCount=current;

        // Recovery
        if(staleLoops>0&&staleLoops%15===0){
            console.error(`[recovery] stale=${staleLoops} reviews=${current}`);
            await findScrollContainer(page);
            await page.mouse.click(300,400).catch(()=>{});
            for(let k=0;k<5;k++){await page.keyboard.press('PageDown').catch(()=>{});await page.waitForTimeout(300);}
        }
        if(staleLoops>0&&staleLoops%40===0){
            await page.evaluate(()=>{
                const sels=['[class*="business-review-view"]','[class*="business-review"]','[data-testid*="review"]'];
                for(const sel of sels){const ns=document.querySelectorAll(sel);if(ns.length){try{ns[ns.length-1].scrollIntoView({behavior:'instant',block:'center'});ns[ns.length-1].click();}catch(_){}return;}}
            }).catch(()=>{});
            await page.waitForTimeout(1000);
        }
        if(staleLoops>0&&staleLoops%80===0){
            await clickReviewsTab(page);
            await page.waitForTimeout(3000);
            await findScrollContainer(page);
        }
        if(staleLoops>0&&staleLoops%120===0){
            await resetScroll(page);
            await page.waitForTimeout(2000);
        }

        // Stop if truly stuck
        if(i>150&&(i-lastNewAt)>250){console.error(`[loop] stuck at ${current}, stop`);break;}

        // Scroll amount: vary between small and large
        const amt=i%5===0?2500:(800+Math.floor(Math.random()*500));
        await doScroll(page,amt);

        if(i>0&&i%50===0) await findScrollContainer(page);
    }

    for(const r of networkMap.values()) addToMap(all,r);
    return Array.from(all.values()).slice(0,maxReviews||MAX_REVIEWS);
}

// ─── Entry point ─────────────────────────────────────────────────────────────
async function main() {
    const url=process.argv[2];
    if(!url){console.log(JSON.stringify({success:false,error:'URL is required'}));process.exitCode=1;return;}

    let browser;
    try {
        const startedAt=Date.now();
        const networkMap=new Map();
        console.error('[parser] start:',url);

        browser=await chromium.launch({
            headless:true,
            args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--disable-features=UseDnsHttpsSvcb,AsyncDns','--disable-web-security','--blink-settings=imagesEnabled=false'],
        });

        const page=await browser.newPage({
            viewport:{width:1440,height:900},
            userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            locale:'ru-RU',
        });
        page.setDefaultTimeout(20000);

        await page.route('**/*',async route=>{
            const type=route.request().resourceType();
            if(['font','media','image'].includes(type)) await route.abort().catch(()=>{});
            else await route.continue().catch(()=>{});
        });

        setupNetworkCollector(page,networkMap);

        await page.goto(url,{waitUntil:'domcontentloaded',timeout:75000});
        await page.waitForLoadState('networkidle',{timeout:30000}).catch(()=>{});
        await page.waitForTimeout(5000);
        await acceptPopups(page);

        if(await detectBot(page)) throw new Error('Yandex bot protection/captcha detected. Try again later.');

        await saveDebug(page,'01-loaded');

        const b1=await extractBusiness(page);
        console.error('[parser] business:',JSON.stringify(b1));

        const tabClicked=await clickReviewsTab(page);
        console.error('[parser] tab clicked:',tabClicked);

        await page.waitForTimeout(4000);
        await acceptPopups(page);
        await clickMoreButtons(page);

        const b2=await extractBusiness(page);
        console.error('[parser] business after tab:',JSON.stringify(b2));

        await saveDebug(page,'02-reviews-tab');
        await page.waitForTimeout(3000);

        const realCount=Math.max(b1.reviewsCount||0,b2.reviewsCount||0);
        const limit=realCount>0?Math.min(realCount,MAX_REVIEWS):MAX_REVIEWS;
        console.error(`[parser] limit:${limit} realCount:${realCount}`);

        const reviews=await collectAllReviews(page,limit,startedAt,networkMap);

        await saveDebug(page,'03-done');

        const name=b1.name||b2.name||'Yandex Organization';
        const averageRating=b1.averageRating||b2.averageRating||null;
        const ratingsCount=b1.ratingsCount||b2.ratingsCount||0;
        const finalCount=Math.max(realCount,reviews.length);
        const expected=finalCount>0?Math.min(finalCount,MAX_REVIEWS):reviews.length;

        if(REQUIRE_ALL&&expected>0&&reviews.length<expected)
            throw new Error(`Parser collected only ${reviews.length} of ${expected} reviews.`);

        const warning=reviews.length<expected?`Collected ${reviews.length} of ${expected} available reviews.`:null;
        console.error(`[parser] done: ${reviews.length}/${expected}`);

        console.log(JSON.stringify({
            success:true,
            data:{name,average_rating:averageRating,ratings_count:ratingsCount,reviews_count:finalCount,parsed_reviews_count:reviews.length,parse_warning:warning,reviews},
        }));
    } catch(error) {
        console.error('[parser] error:',error.message);
        console.log(JSON.stringify({success:false,error:error.message}));
        process.exitCode=1;
    } finally {
        if(browser) await browser.close().catch(()=>{});
    }
}

main();
