<?php
/**
 * Applies the hamburger-in-topbar + theme toggle update to all DRIMS pages.
 * Run once from browser: http://localhost/ICLHO_Route/apply_fix.php
 */

$themeJS = <<<'JS'
// Theme toggle
(function(){
  const root=document.documentElement,btn=document.getElementById('themeToggle'),icon=document.getElementById('themeIcon');
  if(!btn)return;
  const MOON='<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
  const SUN='<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
  function apply(t){root.setAttribute('data-theme',t);localStorage.setItem('drims-theme',t);if(icon)icon.innerHTML=t==='light'?MOON:SUN;}
  apply(localStorage.getItem('drims-theme')||'dark');
  btn.addEventListener('click',()=>apply(root.getAttribute('data-theme')==='light'?'dark':'light'));
})();
JS;

$themeBtn = '<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme" title="Toggle light/dark mode">
    <svg id="themeIcon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
  </button>';

$menuBtn = '<button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar"><span></span><span></span><span></span></button>';

$files = [
    'employees.php',
    'employee_dashboard.php',
    'inbox.php',
    'file_management.php',
    'new_document.php',
    'messages.php',
];

$dir = __DIR__;

foreach ($files as $file) {
    $path = $dir . '/' . $file;
    if (!file_exists($path)) { echo "SKIP (not found): $file<br>"; continue; }
    $c = file_get_contents($path);
    $changed = false;

    // 1. Add menu-toggle inside top-bar (after <div class="top-bar">)
    if (strpos($c, 'id="menuToggle"') !== false && strpos($c, '<div class="top-bar">') !== false) {
        // Check if menuToggle is ALREADY inside top-bar
        $topBarPos = strpos($c, '<div class="top-bar">');
        $topBarEnd = strpos($c, '</div>', $topBarPos);
        $topBarContent = substr($c, $topBarPos, $topBarEnd - $topBarPos);
        if (strpos($topBarContent, 'menuToggle') === false) {
            // Move toggle inside top-bar
            $c = preg_replace('/<div class="menu-button-container"[^>]*>\s*<button class="menu-toggle"[^>]*>[\s\S]*?<\/button>\s*<\/div>\s*/i', '', $c);
            $c = str_replace('<div class="top-bar">', '<div class="top-bar">' . "\n  " . $menuBtn, $c);
            $changed = true;
        }
    }

    // 2. Add theme toggle button before </div> closing the top-bar (only if not already there)
    if (strpos($c, 'themeToggle') === false && strpos($c, 'class="top-bar"') !== false) {
        // Find the closing of top-bar: the first </div> after top-bar
        $c = preg_replace('/(<div class="top-bar">[\s\S]*?)([ \t]*<\/div>)/m', '$1  ' . $themeBtn . "\n$2", $c, 1);
        $changed = true;
    }

    // 3. Remove old menuBtnContainer references from JS (cleanup)
    $c = preg_replace('/\s*menuBtn\s*=\s*document\.getElementById\([\'"]menuBtnContainer[\'"]\)[,;]?/m', '', $c);
    $c = preg_replace('/menuBtn\.classList\.(add|remove)\([^)]+\);?\s*/m', '', $c);

    // 4. Add theme JS before </script> closing tag (only if not already there)
    if (strpos($c, 'drims-theme') === false && strpos($c, '</script>') !== false) {
        $c = preg_replace('/(<\/script>)(?![\s\S]*<\/script>)/', $themeJS . "\n$1", $c);
        $changed = true;
    }

    if ($changed) {
        file_put_contents($path, $c);
        echo "Updated: $file<br>";
    } else {
        echo "No changes needed: $file<br>";
    }
}

echo "<br><strong>Done!</strong>";
unlink(__FILE__);
