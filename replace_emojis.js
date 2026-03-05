const fs = require('fs');
const path = require('path');

const emojiMap = {
    '⚙️': '<i class="fa-solid fa-gear"></i>',
    '🔒': '<i class="fa-solid fa-lock"></i>',
    '🚪': '<i class="fa-solid fa-right-from-bracket"></i>',
    '✅': '<i class="fa-solid fa-check-circle"></i>',
    '✏️': '<i class="fa-solid fa-pen"></i>',
    '📅': '<i class="fa-regular fa-calendar-days"></i>',
    '💾': '<i class="fa-solid fa-floppy-disk"></i>',
    '📷': '<i class="fa-solid fa-camera"></i>',
    '📸': '<i class="fa-solid fa-camera"></i>',
    '📊': '<i class="fa-solid fa-chart-column"></i>',
    '📈': '<i class="fa-solid fa-chart-line"></i>',
    '🎓': '<i class="fa-solid fa-graduation-cap"></i>',
    '👨‍🏫': '<i class="fa-solid fa-chalkboard-user"></i>',
    '👩‍🎓': '<i class="fa-solid fa-user-graduate"></i>',
    '⏱️': '<i class="fa-solid fa-stopwatch"></i>',
    '⏰': '<i class="fa-solid fa-clock"></i>',
    '⚠️': '<i class="fa-solid fa-triangle-exclamation"></i>',
    '❌': '<i class="fa-solid fa-xmark"></i>',
    '👁️': '<i class="fa-solid fa-eye"></i>',
    '📍': '<i class="fa-solid fa-location-dot"></i>',
    '📋': '<i class="fa-solid fa-clipboard"></i>',
    '🛑': '<i class="fa-solid fa-stop"></i>',
    '🕐': '<i class="fa-regular fa-clock"></i>',
    '🔑': '<i class="fa-solid fa-key"></i>',
    '📱': '<i class="fa-solid fa-mobile-screen-button"></i>',
    '⬇️': '<i class="fa-solid fa-download"></i>',
    '🟢': '<i class="fa-solid fa-circle" style="color: #10b981;"></i>',
    '🔴': '<i class="fa-solid fa-circle" style="color: #ef4444;"></i>',
    '〰️': '<i class="fa-solid fa-minus"></i>',
    '➕': '<i class="fa-solid fa-plus"></i>',
    '🔍': '<i class="fa-solid fa-magnifying-glass"></i>',
    '🔵': '<i class="fa-solid fa-circle" style="color: #3b82f6;"></i>',
    '🍩': '<i class="fa-solid fa-chart-pie"></i>',
    '▶': '<i class="fa-solid fa-play"></i>',
    '⬛': '<i class="fa-solid fa-square"></i>',
    '🔁': '<i class="fa-solid fa-rotate-right"></i>',
    '👤': '<i class="fa-solid fa-user"></i>',
    '📥': '<i class="fa-solid fa-download"></i>',
    '📚': '<i class="fa-solid fa-book"></i>'
};

const fontAwesomeLink = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

function processFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    let modified = false;

    // Add FontAwesome if it is an HTML file and doesn't have it
    if (filePath.endsWith('.html') && !content.includes('font-awesome/6.4.0/css/all.min.css')) {
        content = content.replace('</head>', `    ${fontAwesomeLink}\n</head>`);
        modified = true;
    }

    // Replace emojis
    Object.keys(emojiMap).forEach(emoji => {
        const parts = content.split(emoji);
        if (parts.length > 1) {
            content = parts.join(emojiMap[emoji]);
            modified = true;
        }
    });

    if (modified) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log(`Updated: ${filePath}`);
    }
}

function walkDir(dir) {
    if (!fs.existsSync(dir)) return;
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            walkDir(fullPath);
        } else if (fullPath.endsWith('.html') || fullPath.endsWith('.js') || fullPath.endsWith('.php')) {
            processFile(fullPath);
        }
    }
}

walkDir(path.join(__dirname, 'frontend'));
walkDir(path.join(__dirname, 'backend'));
console.log('Done!');
