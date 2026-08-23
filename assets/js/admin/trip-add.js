  document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('trip-plan-form');

        if (form) {
            form.addEventListener('submit', (e) => {
                const depDate = document.querySelector('input[name="departureDate"]').value;
                const depTime = document.querySelector('input[name="departureTime"]').value;
                const arrDate = document.querySelector('input[name="arrivalDate"]').value;
                const arrTime = document.querySelector('input[name="arrivalTime"]').value;

                const departureDateTime = new Date(`${depDate}T${depTime}`);
                const arrivalDateTime = new Date(`${arrDate}T${arrTime}`);

                if (arrivalDateTime <= departureDateTime) {
                    e.preventDefault();
                    alert("Erreur de cohérence : La date et l'heure d'arrivée doivent être postérieures à celles du départ du bus.");
                }
            });
        }
    });
