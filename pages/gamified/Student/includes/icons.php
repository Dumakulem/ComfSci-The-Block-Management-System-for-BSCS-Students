<?php
function ic(string $n): string {
  $IC = [
    'grid'=>'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
    'user'=>'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 4-6 8-6s8 2 8 6',
    'cup'=>'M5 9h11v5a4 4 0 01-4 4H9a4 4 0 01-4-4zM16 10h2a2 2 0 010 4h-2M8 3v3M12 3v3',
    'cal'=>'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
    'chk'=>'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4M9.5 15l2 2 3.5-3.5',
    'bars'=>'M5 20V10M12 20V4M19 20v-7',
    'doc'=>'M6 3h8l4 4v14H6zM9 12h6M9 16h6',
    'bell'=>'M6 17V11a6 6 0 0112 0v6l2 2H4zM10 21h4',
    'cloud'=>'M7 17a4 4 0 010-8 5 5 0 019.6-1.2A4 4 0 0117 17M12 12v7M9 16l3 3 3-3',
    'out'=>'M10 4H5a1 1 0 00-1 1v14a1 1 0 001 1h5M15 8l4 4-4 4M19 12H9',
    'gear'=>'M12 15a3 3 0 100-6 3 3 0 000 6zM12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1'
  ];
  $IX = [
    'grid'=>'<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
    'user'=>'<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
    'cup'=>'<path d="M10 2v2M14 2v2M6 2v2M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/>',
    'chk'=>'<path d="M8 2v4M16 2v4M3 10h18M9 16l2 2 4-4"/><rect x="3" y="4" width="18" height="18" rx="2"/>',
    'cal'=>'<path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="18" rx="2"/>',
    'bars'=>'<path d="M3 3v16a2 2 0 0 0 2 2h16M18 17V9M13 17V5M8 17v-3"/>',
    'doc'=>'<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7zM14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8"/>',
    'bell'=>'<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'cloud'=>'<path d="M12 13v8l-4-4M12 21l4-4M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/>',
    'out'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>'
  ];
  $body = $IX[$n] ?? '<path d="'.($IC[$n] ?? '').'"/>';
  return '<svg class="i" viewBox="0 0 24 24">'.$body.'</svg>';
}