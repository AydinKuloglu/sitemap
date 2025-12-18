# Ordered Sitemap WordPress Eklentisi

Bu depo, WordPress için basit ve düzenlenebilir bir site haritası eklentisi içerir. Eklenti, sayfaları ve blog yazılarını ayrı listeler halinde gösterir ve her liste için sıralama tercihlerini seçmenizi sağlar.

## Özellikler

- Sayfalar ve blog yazıları ayrı başlıklar altında listelenir.
- Sıralama tercihleriniz (menü sırası, başlık veya tarih) yönetici panelinde ayarlanabilir.
- `[ordered_sitemap]` kısa kodu ile herhangi bir sayfaya yerleştirilebilir.
- Kısa kod parametreleriyle sayfa ve yazı sıralamaları gerektiğinde değiştirilebilir:
  - `page_order="title_asc"` veya `menu_asc`, `title_desc`, `date_asc`, `date_desc`
  - `post_order="date_desc"` veya `title_asc`, `title_desc`, `date_asc`

## Kurulum

1. `ordered-sitemap` klasörünü WordPress kurulumunuzdaki `wp-content/plugins/` dizinine kopyalayın.
2. WordPress yönetici panelinden **Eklentiler** bölümüne gidip **Ordered Sitemap** eklentisini etkinleştirin.
3. **Ayarlar → Ordered Sitemap** sayfasına giderek sıralama tercihlerinizi seçin.
4. Site haritasını göstermek istediğiniz sayfaya `[ordered_sitemap]` kısa kodunu ekleyin.
