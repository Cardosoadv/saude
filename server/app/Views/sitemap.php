<?php
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php if (isset($sitemap) && is_array($sitemap)): ?>
    <?php foreach ($sitemap as $link): ?>
        <url>
            <loc><?= htmlspecialchars($link['url']) ?></loc>
            <?php if (!empty($link['last_modified'])): ?>
            <lastmod><?= date('Y-m-d', strtotime($link['last_modified'])) ?></lastmod>
            <?php endif; ?>
            <?php if (!empty($link['changefreq'])): ?>
            <changefreq><?= htmlspecialchars($link['changefreq']) ?></changefreq>
            <?php endif; ?>
            <?php if (!empty($link['priority'])): ?>
            <priority><?= htmlspecialchars($link['priority']) ?></priority>
            <?php endif; ?>
        </url>
    <?php endforeach; ?>
<?php endif; ?>
</urlset>
