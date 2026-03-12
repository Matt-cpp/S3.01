document.querySelectorAll('.pagination-buttons a').forEach(link => {
    link.addEventListener('click', function () {
        this.querySelector('button').disabled = true;
        this.querySelector('button').textContent = 'Chargement...';
    });
});