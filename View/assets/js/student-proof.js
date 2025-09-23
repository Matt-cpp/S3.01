
function getRealTime() {
    var now = new Date();
    return new Date(now.toLocaleString("en-US", {timeZone: "Europe/Paris"}));
}

function toggleCustomReason() {
    var select = document.getElementById('absence_reason');
    var customdiv = document.getElementById('custom_reason');
    var custominput = document.getElementById('other_reason');

    if (select.value === 'Autre') {
        customdiv.style.display = 'block';
        custominput.required = true;
    } else {
        customdiv.style.display = 'none';
        custominput.required = false;
        custominput.value = '';
    }
}

function validateDates() {
    var dateStart = document.getElementById('datetime_start').value;
    var dateEnd = document.getElementById('datetime_end').value;

    // Utiliser l'heure réelle pour les comparaisons
    var realTime = getRealTime();

    // Validation of the end date not being more than 48 hours in the past
    if (dateEnd) {
        var fin = new Date(dateEnd);
        var minDate = new Date(realTime.getTime() - (48 * 60 * 60 * 1000));
        
        if (fin < minDate) {
            alert('La date de fin ne peut pas être antérieure à plus de 48h.');
            document.getElementById('datetime_end').value = '';
            return false;
        }
    }

    // Validation of the end date being after the start date
    if (dateStart && dateEnd) {
        var debut = new Date(dateStart);
        var fin = new Date(dateEnd);
        if (fin <= debut) {
            alert('La date/heure de fin doit être postérieure à la date/heure de début.');
            document.getElementById('datetime_end').value = '';
            return false;
        }
    }
    
    return true;
}

window.addEventListener('DOMContentLoaded', function() {
    document.getElementById('datetime_start').addEventListener('change', function() {
        var dateEnd = document.getElementById('datetime_end');
        if (dateEnd.value) {
            validateDates();
        }
        dateEnd.min = this.value;
    });
    
    document.getElementById('datetime_end').addEventListener('change', function() {
        var dateEnd = document.getElementById('datetime_end');
        if (dateEnd.value) {
            validateDates();
        }
    });
    
    // Validate dates on form submission
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!validateDates()) {
            e.preventDefault();
        }
    });
});