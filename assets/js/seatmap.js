document.addEventListener('DOMContentLoaded', () => {

    console.log('chargé')

    const seatmap = document.querySelector('.agency-bus-matrix');

    if (!seatmap) return;

    let selectedSeat = null;

    seatmap.addEventListener('click', (e) => {

        const seat = e.target.closest('[data-action="select-seat"]');

        if (!seat) return;

        // sécurité : siège réservé
        if (seat.classList.contains('is-booked') || seat.dataset.booked === "1") {
            return;
        }

        // toggle off
        if (selectedSeat === seat) {

            seat.classList.remove('is-selected');
            selectedSeat = null;

            updateTicketForm(null, null);
            return;
        }

        // reset ancien
        if (selectedSeat) {
            selectedSeat.classList.remove('is-selected');
        }

        // select nouveau
        selectedSeat = seat;
        seat.classList.add('is-selected');

        updateTicketForm(
            seat.dataset.seatId,
            seat.dataset.seatNumber,
            seat.dataset.seatRow,
            seat.dataset.seatCol,
        );

        console.log('Selected seat:', seat.dataset.seatId, seat.dataset.seatNumber,);

    });

});


function updateTicketForm(id, label, row, col) {

    const inputId = document.getElementById('ticket-form-seat-id');
    const inputLabel = document.getElementById('ticket-form-seat-label');
    const display = document.getElementById('ticket-form-seat-display');

    if (!inputId || !display) return;

    if (id) {

        inputId.value = id;

        if (inputLabel) {
            inputLabel.value = label;
        }

        display.textContent =
            `Siège ${label} (R${row} - C${col})`;

        display.style.color = '#2e91ce';

    } else {

        inputId.value = '';

        if (inputLabel) {
            inputLabel.value = '';
        }

        display.textContent = 'Aucun siège sélectionné';
        display.style.color = '';
    }
}