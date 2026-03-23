document.querySelectorAll('.pagination-container').forEach(container => {
    container.addEventListener('click', function (event) {
        const clickedLink = event.target.closest('a.btn-pagination');

        if (!clickedLink || !container.contains(clickedLink)) {
            return;
        }

        if (clickedLink.classList.contains('btn-pagination-disabled')) {
            event.preventDefault();
            return;
        }

        clickedLink.classList.add('btn-pagination-disabled');
        clickedLink.style.pointerEvents = 'none';
        clickedLink.textContent = 'Chargement...';
    });
});