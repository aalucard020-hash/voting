
// =====================================
// KAKANG SENDUK PONOROGO
// MAIN JAVASCRIPT
// =====================================

document.addEventListener("DOMContentLoaded", function () {

    // Menutup navbar setelah menu dipilih di mobile
    const navLinks = document.querySelectorAll(
        ".navbar-nav .nav-link"
    );

    const navbarCollapse = document.querySelector(
        "#navbarMenu"
    );

    navLinks.forEach(function (link) {
        link.addEventListener("click", function () {

            if (
                navbarCollapse &&
                navbarCollapse.classList.contains("show")
            ) {
                const bsCollapse =
                    bootstrap.Collapse.getOrCreateInstance(
                        navbarCollapse
                    );

                bsCollapse.hide();
            }

        });
    });

});
