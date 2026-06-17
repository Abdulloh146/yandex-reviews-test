{{-- faqat statusBox rangini warning uchun yaxshilangan mini patch.
Agar to'liq blade kerak bo'lsa oldingi dashboard.blade.php ichida renderOrganization() funksiyasidagi success blokni shunga almashtiring:

if (organization.parse_status === 'success') {
    if (organization.parse_error) {
        el.statusBox.textContent = `Парсинг завершён с предупреждением: ${organization.parse_error}`;
        el.statusBox.classList.add('bg-yellow-50', 'text-yellow-700');
    } else {
        el.statusBox.textContent = `Парсинг завершён. Загружено ${parsed} из ${yandexCount} отзывов.`;
        el.statusBox.classList.add('bg-green-50', 'text-green-700');
    }
    el.statusBox.classList.remove('hidden');
}

--}}