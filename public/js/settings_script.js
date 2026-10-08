document.addEventListener('DOMContentLoaded', () => {
    const moonBtn = document.getElementById('moon-btn');

    if (moonBtn) {
        const icon = moonBtn.querySelector('i');
        const textSpan = moonBtn.querySelector('span'); 

        // 1. Panimulang Sync: I-update ang UI base sa class na binato ng PHP server
        if (document.body.classList.contains('light-mode')) {
            moonBtn.style.color = '#f59e0b'; // Amber yellow
            if (icon) icon.className = 'fas fa-sun';
            if (textSpan) textSpan.textContent = 'Dark Mode';
        } else {
            moonBtn.style.color = '#38BDF8'; // Sky blue
            if (icon) icon.className = 'fas fa-moon';
            if (textSpan) textSpan.textContent = 'Light Mode';
        }

        moonBtn.addEventListener('click', () => {
            // I-toggle ang light-mode class sa body tag
            const isLight = document.body.classList.toggle('light-mode');
            let currentTheme = 'dark';

            if (isLight) {
                currentTheme = 'light';
                if (icon) icon.className = 'fas fa-sun';
                if (textSpan) textSpan.textContent = 'Dark Mode';
                moonBtn.style.color = '#f59e0b';
                
                // Siguraduhing may global toast instance ka bago tawagin ito
                if (typeof toast !== 'undefined') toast.show('Light mode enabled!', 'success', 2000);
            } else {
                if (icon) icon.className = 'fas fa-moon';
                if (textSpan) textSpan.textContent = 'Light Mode';
                moonBtn.style.color = '#38BDF8';
                
                if (typeof toast !== 'undefined') toast.show('Dark mode enabled!', 'success', 2000);
            }

            // 3. I-save ang state sa Document Cookies (Valid for 30 Days) para basahin ng PHP sa susunod na load
            document.cookie = "theme=" + currentTheme + "; max-age=" + (30 * 24 * 60 * 60) + "; path=/; SameSite=Lax";
        });
    }
});