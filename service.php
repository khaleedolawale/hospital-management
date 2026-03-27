<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Our Services - Hospital Management System</title>
  <link rel="stylesheet" href="./assets/css/service.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- Navigation Bar -->
  <nav class="navbar">
    <div class="container">
      <div class="logo">🏥 HMS</div>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="contact.php">Contacts</a></li>
        <li><a href="register.php">Register</a></li>
        <li><a href="login.php">Login</a></li>
      </ul>
      <span class="menu-icon" onclick="toggleMenu()"><i class="fas fa-bars"></i></span>
    </div>
  </nav>

  <!-- Hero Section -->
  <header class="hero">
    <div class="overlay"></div>
    <div class="hero-content">
      <h1>Our Services</h1>
      <p>Explore the features that make our Hospital Management System unique.</p>
    </div>
  </header>

  <!-- Services Section -->
  <section class="services">
    <div class="container">
      <div class="service-card">
        <div class="icon"><i class="fas fa-hospital-user"></i></div>
        <div class="text">
          <h2>Trusted by Top Hospitals and Clinics</h2>
          <p>
            Our platform is used by leading hospitals, clinics, and private practices across the region. 
            It offers a scalable solution that adapts to both small healthcare centers and large multi-department 
            hospitals. With proven reliability, you can trust our system to manage operations without disruption.
          </p>
        </div>
      </div>

      <div class="service-card">
        <div class="icon"><i class="fas fa-calendar-check"></i></div>
        <div class="text">
          <h2>Seamless Appointment Booking & Patient Tracking</h2>
          <p>
            Patients can book appointments easily through the system, reducing long queues and manual paperwork. 
            Doctors get instant notifications, and administrators can manage schedules effectively. 
            This ensures smoother communication between staff and patients, improving overall satisfaction.
          </p>
        </div>
      </div>

      <div class="service-card">
        <div class="icon"><i class="fas fa-shield-alt"></i></div>
        <div class="text">
          <h2>Secure, Encrypted Data Handling</h2>
          <p>
            Data privacy is a top priority. All patient and hospital data is stored securely with encryption protocols, 
            ensuring compliance with global healthcare data standards. Only authorized users can access sensitive information, 
            keeping both patients and staff protected at all times.
          </p>
        </div>
      </div>

      <div class="service-card">
        <div class="icon"><i class="fas fa-headset"></i></div>
        <div class="text">
          <h2>24/7 System Availability and Support</h2>
          <p>
            Healthcare doesn’t stop, and neither do we. Our system is built to run round-the-clock, 
            with minimal downtime. A dedicated support team is also available to provide quick help, 
            ensuring that your hospital operations continue without interruption.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Call to Action -->
  <section class="cta">
    <div class="container">
      <h2>Ready to Transform Your Hospital?</h2>
      <p>Join hundreds of hospitals already using our management system for better healthcare delivery.</p>
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
