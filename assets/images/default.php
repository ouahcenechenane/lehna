<?php
/**
 * assets/images/default.jpg
 * Ce fichier génère une image placeholder SVG pour les produits sans image
 * En production, remplacer par un vrai fichier default.jpg
 */

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg width="300" height="300" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
  <rect width="300" height="300" fill="#1c1a15"/>
  <rect width="300" height="300" fill="url(#p)"/>
  <defs>
    <pattern id="p" width="20" height="20" patternUnits="userSpaceOnUse">
      <circle cx="10" cy="10" r="1" fill="#2e2b22"/>
    </pattern>
  </defs>
  <rect x="80" y="80" width="140" height="140" rx="20" fill="#2e2b22"/>
  <text x="150" y="145" text-anchor="middle" font-size="48" fill="#d4a35a">📦</text>
  <text x="150" y="185" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#7a7260">Image produit</text>
</svg>
