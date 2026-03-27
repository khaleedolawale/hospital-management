<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Us - Hospital Management System</title>
  <link rel="stylesheet" href="./assets/css/contact.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar">
    <div class="container">
      <div class="logo">🏥 HMS</div>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="service.php">Our Services</a></li>
        <li><a href="register.php">Register</a></li>
        <li><a href="login.php">Login</a></li>
      </ul>
      <span class="menu-icon" onclick="toggleMenu()"><i class="fas fa-bars"></i></span>
    </div>
  </nav>

  <!-- Hero / Message -->
  <header class="contact-hero">
    <div class="overlay"></div>
    <div class="hero-content">
      <!-- <h1>Get in Touch</h1>
      <p>We’d love to hear from you. Reach out today!</p> -->
    </div>
  </header>

  <!-- Info Boxes -->
  <section class="contact-info">
    <div class="container info-grid">
      <div class="info-box">
        <i class="fas fa-map-marker-alt"></i>
        <h3>Our Address</h3>
        <p>123 Health Street,<br> Lagos, Nigeria.</p>
      </div>
      <div class="info-box">
        <i class="fas fa-envelope"></i>
        <h3>Email & Phone</h3>
        <p>Email: info@hospital.com<br>Phone: +234 800 123 4567</p>
      </div>
    </div>
  </section>

  <?php if (isset($_GET['msg'])): ?>
    <div class="alert">
        <?php echo htmlspecialchars($_GET['msg']); ?>
    </div>
<?php endif; ?>



  <!-- Contact Form -->
  <section class="contact-form">
    <div class="container">
      <h2>Send Us a Message</h2>
      <form action="./includes/send_message.php" method="POST">
        <div class="form-group">
          <input type="text" name="name" placeholder="Your Name" required>
        </div>
        <div class="form-group">
          <input type="email" name="email" placeholder="Your Email" required>
        </div>
        <div class="form-group">
          <input type="text" name="subject" placeholder="Subject" required>
        </div>
        <div class="form-group">
          <textarea name="message" rows="5" placeholder="Your Message" required></textarea>
        </div>
        <button type="submit" class="btn primary">Send Message</button>
      </form>
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
