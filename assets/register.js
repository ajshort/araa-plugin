document.addEventListener("DOMContentLoaded", function() {
    var au = document.getElementById("state-au-container");
    var intl = document.getElementById("state-container");

    country.onchange = function() {
        var isAu = (this.value == "AU");

        au.style.display = (isAu ? "block" : "none");
        intl.style.display = (isAu ? "none" : "block");
    };
    country.onchange();
});
