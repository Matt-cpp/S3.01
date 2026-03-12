document.querySelectorAll('.pagination-container').forEach(link => {
    link.addEventListener('click', function () {
        this.querySelector('a').disabled = true;
        this.querySelector('a').textContent = 'Chargement...';
    });
});