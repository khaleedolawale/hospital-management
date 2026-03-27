<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us - Hospital Management System</title>
    <link rel="stylesheet" href="./assets/css/about.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container">
            <!-- Left: Logo -->
            <div class="logo">🏥 HMS</div>

            <!-- Middle: Links -->
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php">Home</a></li>
                <li><a href="service.php">Our Services</a></li>
                <li><a href="contact.php">Contacts</a></li>
                <li><a href="register.php">Register</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>

            <!-- Right: Menu Icon (mobile) -->
            <span class="menu-icon" onclick="toggleMenu()"><i class="fas fa-bars"></i></span>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="overlay"></div>
        <div class="hero-content">
            <h1>About Our Hospital</h1>
            <p>Committed to delivering world-class healthcare solutions for all.</p>
        </div>
    </header>

    <!-- Who We Are -->
    <section class="about-section">
        <div class="container">
            <h2>Who We Are</h2>
            <p>
                Our Hospital Management System (HMS) was created with a simple but powerful vision: 
                to simplify healthcare management for patients, doctors, and administrators. 
                We believe in leveraging technology to provide efficient, secure, and 
                accessible healthcare services to everyone, regardless of location. 
                From patient records to doctor scheduling, our platform is designed 
                to ensure smooth operations that enhance the quality of care delivered.
            </p>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="mission-vision">
        <div class="container grid">
            <div class="card">
                <i class="fas fa-bullseye"></i>
                <h3>Our Mission</h3>
                <p>
                    To improve healthcare delivery through innovation and 
                    user-friendly technology, empowering hospitals and clinics 
                    to serve patients more effectively and efficiently.
                </p>
            </div>
            <div class="card">
                <i class="fas fa-eye"></i>
                <h3>Our Vision</h3>
                <p>
                    To become the most trusted hospital management platform in Africa, 
                    driving healthcare accessibility, transparency, and quality for all.
                </p>
            </div>
        </div>
    </section>

    <!-- Our Team -->
    <section class="team">
        <div class="container">
            <h2>Meet Our Team</h2>
            <div class="team-grid">   
                <div class="team-member">
                    <img src="./assets/images/nurse1.jpg" alt="Nurse">
                    <h4>Nurse Samuel Ade</h4>
                    <p>Head Nurse</p>
                </div>
                <div class="team-member">
                    <img src="./assets/images/doctor1.avif" alt="Doctor">
                    <h4>Dr. Aisha Bello</h4>
                    <p>Chief Medical Officer</p>
                </div>
                <div class="team-member">
                    <img src="./assets/images/nurse2.png" alt="Admin">
                    <h4>John Okafor</h4>
                    <p>Hospital Administrator</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="why-us">
        <div class="container">
            <h2>Why Choose Us?</h2>
            <ul>
                <li><i class="fas fa-check-circle"></i> Trusted by top hospitals and clinics.</li>
                <li><i class="fas fa-check-circle"></i> Seamless appointment booking and patient tracking.</li>
                <li><i class="fas fa-check-circle"></i> Secure, encrypted data handling.</li>
                <li><i class="fas fa-check-circle"></i> 24/7 system availability and support.</li>
            </ul>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Experience Seamless Healthcare?</h2>
            <p>Join our system today and make healthcare simple, secure, and accessible.</p>
            <a href="register.php" class="btn primary">Get Started</a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Hospital Management System. All Rights Reserved.</p>

        <button id="backToTop"><i class="fas fa-arrow-up"></i></button>

    </footer>

    <script>
        function toggleMenu() {
            document.getElementById("navLinks").classList.toggle("active");
        }

        let lastScrollY = window.scrollY;
  const navbar = document.querySelector(".navbar");

  window.addEventListener("scroll", () => {
    if (window.scrollY > lastScrollY) {
      // scrolling down
      navbar.style.top = "-80px"; // hide nav
    } else {
      // scrolling up
      navbar.style.top = "0";
    }
    lastScrollY = window.scrollY;
  });

        const backToTopButton = document.getElementById("backToTop");

  window.onscroll = function () {
    if (
      document.body.scrollTop > 200 ||
      document.documentElement.scrollTop > 200
    ) {
      backToTopButton.classList.add("show");
    } else {
      backToTopButton.classList.remove("show");
    }
  };

  backToTopButton.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
    </script>
</body>
</html>
