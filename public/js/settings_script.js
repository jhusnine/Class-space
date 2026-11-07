document.addEventListener('DOMContentLoaded', () => {
    const moonBtn = document.getElementById('moon-btn');

    if (moonBtn) {
        const icon = moonBtn.querySelector('i');
        const textSpan = moonBtn.querySelector('span');


        if (document.body.classList.contains('light-mode')) {
            moonBtn.style.color = '#f59e0b';
            if (icon) icon.className = 'fas fa-sun';
            if (textSpan) textSpan.textContent = 'Dark Mode';
        } else {
            moonBtn.style.color = '#38BDF8';
            if (icon) icon.className = 'fas fa-moon';
            if (textSpan) textSpan.textContent = 'Light Mode';
        }

        moonBtn.addEventListener('click', () => {

            const isLight = document.body.classList.toggle('light-mode');
            let currentTheme = 'dark';

            if (isLight) {
                currentTheme = 'light';
                if (icon) icon.className = 'fas fa-sun';
                if (textSpan) textSpan.textContent = 'Dark Mode';
                moonBtn.style.color = '#f59e0b';


                if (typeof toast !== 'undefined') toast.show('Light mode enabled!', 'success', 2000);
            } else {
                if (icon) icon.className = 'fas fa-moon';
                if (textSpan) textSpan.textContent = 'Light Mode';
                moonBtn.style.color = '#38BDF8';

                if (typeof toast !== 'undefined') toast.show('Dark mode enabled!', 'success', 2000);
            }


            document.cookie = "theme=" + currentTheme + "; max-age=" + (30 * 24 * 60 * 60) + "; path=/; SameSite=Lax";
        });
    }
});
