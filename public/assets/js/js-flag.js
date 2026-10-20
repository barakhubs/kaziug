// Loaded synchronously in <head>: lets CSS style JS-only UI (like the mobile filter panel)
// before first paint, while pages without JavaScript keep their plain layout.
document.documentElement.classList.add('js');
