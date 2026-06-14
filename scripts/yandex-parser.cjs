const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { chromium } = require('playwright');

const tempDir = path.join(process.cwd(), 'storage', 'app', 'playwright-temp');
fs.mkdirSync(tempDir, { recursive: true });

process.env.TEMP = tempDir;
process.env.TMP = tempDir;
process.env.TMPDIR = tempDir;

function normalizeText(text) {
    return String(text || '')
        .replace(/\u00A0/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function numberFromText(text) {
    if (!text) return null;

    const match = String(text).replace(',', '.').match(/[1-5](?:\.\d)?/);

    if (!match) return null;

    const num = parseFloat(match[0]);

    return Number.isFinite(num) ? num : null;
}

function intFromText(text) {
    if (!text) return 0;

    const cleaned = String(text)
        .replace(/\u00A0/g, ' ')
        .replace(/[^\d]/g, '');

    const num = parseInt(cleaned, 10);

    return Number.isFinite(num) ? num : 0;
}

function makeHash(text) {
    return crypto
        .createHash('sha1')
        .update(String(text || ''), 'utf8')
        .digest('hex');
}

async function saveDebug(page) {
    const debugDir = path.join(process.cwd(), 'storage', 'app', 'parser-debug');
    fs.mkdirSync(debugDir, { recursive: true });

    await page.screenshot({
        path: path.join(debugDir, 'yandex-page.png'),
        fullPage: true,
    });

    fs.writeFileSync(
        path.join(debugDir, 'yandex-page.html'),
        await page.content(),
        'utf8'
    );
}

async function safeText(page, selectors) {
    for (const selector of selectors) {
        try {
            const locator = page.locator(selector).first();

            if (await locator.count()) {
                const text = await locator.innerText({ timeout: 3000 });

                if (normalizeText(text)) {
                    return normalizeText(text);
                }
            }
        } catch (_) {}
    }

    return null;
}

async function clickByText(page, texts) {
    for (const text of texts) {
        try {
            const locator = page.getByText(text, { exact: false }).first();

            if (await locator.count()) {
                await locator.click({ timeout: 4000 });
                await page.waitForTimeout(3000);
                return true;
            }
        } catch (_) {}
    }

    return false;
}

async function openReviews(page) {
    const opened = await clickByText(page, [
        'Отзывы',
        'отзывов',
        'отзыва',
        'отзыв',
        'Fikrlar',
        'sharh',
    ]);

    if (opened) return true;

    const selectors = [
        '[aria-label*="Отзывы"]',
        '[aria-label*="отзывы"]',
        '[class*="tabs"] [class*="reviews"]',
        '[class*="business-card"] [class*="reviews"]',
    ];

    for (const selector of selectors) {
        try {
            const locator = page.locator(selector).first();

            if (await locator.count()) {
                await locator.click({ timeout: 4000 });
                await page.waitForTimeout(3000);
                return true;
            }
        } catch (_) {}
    }

    return false;
}

async function scrollPage(page, maxScrolls = 20) {
    for (let i = 0; i < maxScrolls; i++) {
        await page.mouse.wheel(0, 2500);
        await page.waitForTimeout(900);
    }
}

function parseReviewDate(lines) {
    const dateLine = lines.find(line =>
        /(\d{1,2})\s*-?\s*(yanvar|fevral|mart|aprel|may|iyun|iyul|avgust|sentabr|oktabr|noyabr|dekabr|январ|феврал|март|апрел|мая|май|июн|июл|август|сентябр|октябр|ноябр|декабр)\s*(\d{4})?/i.test(line)
    );

    return dateLine || null;
}

function cleanReviewLines(lines) {
    return lines.filter(line => {
        const l = line.toLowerCase();

        if (l === 'obuna') return false;
        if (l.includes('tashkilot javobini')) return false;
        if (l.includes('ответ организации')) return false;
        if (l.includes('подписаться')) return false;
        if (l.includes('shahar bilimdoni')) return false;
        if (l.includes('городской знаток')) return false;
        if (/^\d+$/.test(l)) return false;

        return true;
    });
}

function parseReviewFromRaw(raw) {
    const normalizedRaw = String(raw || '').trim();

    if (normalizedRaw.length < 20) {
        return null;
    }

    let lines = normalizedRaw
        .split('\n')
        .map(line => normalizeText(line))
        .filter(Boolean);

    if (lines.length < 2) {
        return null;
    }

    const authorName = lines[0];

    if (!authorName || authorName.length < 2) {
        return null;
    }

    const bannedAuthors = [
        'Included in the Ultima Guide',
        'Menyu',
        'Sharh',
        'Restoran',
        'Restoran, qahvaxona',
        'Restoran, bar',
        'Restoran, banket zali',
        'Qahvaxona, restoran',
        'Bungacha ochiq',
        'Reyting',
        'Marshrut',
        'Manzil',
        'Biznes uchun',
        'Tashrif buyuruvchanlik va ish rejimi',
        'Promo',
    ];

    if (bannedAuthors.some(item => authorName.toLowerCase().includes(item.toLowerCase()))) {
        return null;
    }

    const reviewDate = parseReviewDate(lines);

    lines = cleanReviewLines(lines);

    let textLines = [...lines];

    if (textLines[0] === authorName) {
        textLines.shift();
    }

    if (reviewDate) {
        textLines = textLines.filter(line => line !== reviewDate);
    }

    textLines = textLines.filter(line => {
        if (line.length < 3) return false;
        if (/^\d\s*-?\s*darajadagi/i.test(line)) return false;
        return true;
    });

    const text = textLines.join('\n').trim();

    if (!text || text.length < 3) {
        return null;
    }

    const bannedTextParts = [
        'Promo',
        'Yandex Maps',
        '© 2001',
        'Xizmat haqida',
        'Foydalanuvchi kelishuvi',
        'Toshkent,',
        'tumani,',
        'koʻchasi',
        'mahalla fuqarolar yigʻini',
        'Bungacha ochiq',
        'ta baho',
        'сўм',
        'soʻm',
        'этаж',
        'Marshrut',
        'Manzil',
    ];

    if (bannedTextParts.some(item => text.toLowerCase().includes(item.toLowerCase()))) {
        return null;
    }

    let rating = null;

    const ratingMatch = normalizedRaw.match(/(?:Оценка|Rating|Baho|Reyting)?\s*([1-5])(?:[.,]0)?/i);

    if (ratingMatch) {
        rating = Number(ratingMatch[1]);
    }

    const hash = makeHash(authorName + reviewDate + text + rating);

    return {
        external_id: `yandex-${hash}`,
        author_name: authorName,
        review_date: null,
        text,
        rating,
    };
}

async function extractReviews(page) {
    const possibleReviewCards = [
        '[class*="business-review-view__info"]',
        '[class*="business-review-view"]',
        '[class*="review-snippet"]',
        '[class*="review-view"]',
        '[data-testid*="review"]',
    ];

    let rawReviews = [];

    for (const selector of possibleReviewCards) {
        try {
            const cards = page.locator(selector);
            const count = await cards.count();

            if (count >= 1) {
                const limit = Math.min(count, 700);

                for (let i = 0; i < limit; i++) {
                    try {
                        const raw = await cards.nth(i).innerText({ timeout: 1500 });

                        if (raw && raw.trim().length > 20) {
                            rawReviews.push(raw);
                        }
                    } catch (_) {}
                }

                if (rawReviews.length > 0) {
                    break;
                }
            }
        } catch (_) {}
    }

    // Agar selectorlar topilmasa, body textdan qo‘pol fallback
    if (rawReviews.length === 0) {
        try {
            const bodyText = await page.locator('body').innerText({ timeout: 3000 });
            const chunks = bodyText
                .split(/(?=\n[A-ZА-ЯЁЎҚҒҲa-zа-яёўқғҳ][^\n]{2,40}\n)/)
                .map(x => x.trim())
                .filter(x => x.length > 50);

            rawReviews = chunks.slice(0, 100);
        } catch (_) {}
    }

    const reviews = [];

    for (const raw of rawReviews) {
        const review = parseReviewFromRaw(raw);

        if (review) {
            reviews.push(review);
        }
    }

    const unique = new Map();

    for (const review of reviews) {
        if (!unique.has(review.external_id)) {
            unique.set(review.external_id, review);
        }
    }

    return Array.from(unique.values());
}

async function extractCounters(page) {
    const pageText = await page.locator('body').innerText().catch(() => '');

    let ratingsCount = 0;
    let reviewsCount = 0;

    const ratingsPatterns = [
        /(\d[\d\s]*)\s*(оценок|оценки|оценка)/i,
        /(\d[\d\s]*)\s*(baholar|baho)/i,
    ];

    const reviewsPatterns = [
        /(\d[\d\s]*)\s*(отзывов|отзыва|отзыв)/i,
        /(\d[\d\s]*)\s*(fikr|sharh)/i,
    ];

    for (const pattern of ratingsPatterns) {
        const match = pageText.match(pattern);
        if (match) {
            ratingsCount = intFromText(match[1]);
            break;
        }
    }

    for (const pattern of reviewsPatterns) {
        const match = pageText.match(pattern);
        if (match) {
            reviewsCount = intFromText(match[1]);
            break;
        }
    }

    return {
        ratingsCount,
        reviewsCount,
        pageText,
    };
}

async function main() {
    const url = process.argv[2];

    if (!url) {
        console.log(JSON.stringify({
            success: false,
            error: 'URL is required',
        }));
        process.exitCode = 1;
        return;
    }

    let browser;

    try {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-features=UseDnsHttpsSvcb,AsyncDns',
                '--disable-blink-features=AutomationControlled',
            ],
        });

        const page = await browser.newPage({
            viewport: {
                width: 1366,
                height: 768,
            },
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        });

        await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout: 60000,
        });

        await page.waitForTimeout(7000);

        await saveDebug(page);

        const title = await page.title();

        const name =
            await safeText(page, [
                'h1',
                '[class*="orgpage-header-view__header"]',
                '[class*="orgpage-header-view__title"]',
                '[class*="business-card-title"]',
                '[class*="card-title"]',
            ]) || title || 'Yandex Organization';

        const ratingText =
            await safeText(page, [
                '[class*="business-rating-badge-view__rating"]',
                '[class*="business-rating"]',
                '[class*="rating-badge"]',
                '[class*="rating"]',
            ]);

        const countersBeforeReviews = await extractCounters(page);

        let averageRating = numberFromText(ratingText);

        if (!averageRating) {
            const ratingMatch = countersBeforeReviews.pageText.match(/([1-5][,.]\d)/);
            averageRating = ratingMatch ? numberFromText(ratingMatch[1]) : null;
        }

        await openReviews(page);
        await scrollPage(page, 25);
        await saveDebug(page);

        const reviews = await extractReviews(page);
        const countersAfterReviews = await extractCounters(page);

        const reviewsCount =
            countersAfterReviews.reviewsCount ||
            countersBeforeReviews.reviewsCount ||
            reviews.length;

        const ratingsCount =
            countersAfterReviews.ratingsCount ||
            countersBeforeReviews.ratingsCount ||
            0;

        console.log(JSON.stringify({
            success: true,
            data: {
                name,
                average_rating: averageRating,
                ratings_count: ratingsCount,
                reviews_count: reviewsCount,
                reviews,
            },
        }));
    } catch (error) {
        console.log(JSON.stringify({
            success: false,
            error: error.message,
        }));

        process.exitCode = 1;
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

main();
