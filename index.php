<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hospital Management System</title>
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container">
            <!-- Left: Logo -->
            <div class="logo">🏥 HMS</div>

            <!-- Middle: Links -->
            <ul class="nav-links" id="navLinks">
                <li><a href="about.php">About Us</a></li>
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
            <h1>Welcome to Our Hospital Management System</h1>
            <p>Efficient. Secure. Accessible healthcare for everyone.</p>
        </div>
    </header>

    <!-- Features Section -->
    <section class="features">
        <h2>Why Choose Our System?</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <i class="fas fa-user-md"></i>
                <h3>Doctor Management</h3>
                <p>Easy scheduling and management of doctors across departments.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-procedures"></i>
                <h3>Patient Records</h3>
                <p>Centralised medical history accessible anytime, anywhere.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Appointments</h3>
                <p>Simple and efficient appointment booking system for patients.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-lock"></i>
                <h3>Secure Access</h3>
                <p>Data encryption and role-based logins to keep information safe.</p>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about">
        <div class="about-container">
            <div class="about-text">
                <h2>About Our Hospital</h2>
                <p>
                    Our hospital management system is designed to provide a seamless experience 
                    for patients, doctors, and administrators. With user-friendly dashboards and 
                    powerful backend support, we ensure smooth operations for better healthcare delivery.
                </p>
                <a href="register.php" class="btn primary">Get Started</a>
            </div>
            <div class="about-image">
                <img src="./assets/images/day-hospital-1.jpg" alt="Hospital" />
            </div>
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

  // Fade in when page loads
  window.addEventListener("load", () => {
    document.body.classList.add("loaded");
  });

  // Fade out when navigating
  document.querySelectorAll("a").forEach(link => {
    if (link.hostname === window.location.hostname) {
      link.addEventListener("click", (e) => {
        const href = link.getAttribute("href");
        if (!href.startsWith("#") && href !== "") {
          e.preventDefault();
          document.body.classList.remove("loaded");
          setTimeout(() => {
            window.location = href;
          }, 600); // same time as CSS transition
        }
      });
    }
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
