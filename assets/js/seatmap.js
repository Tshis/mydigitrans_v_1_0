document.addEventListener('change', (e) => {

    if (e.target.name !== 'seat') return;

    console.log('Seat selected:', e.target);

});