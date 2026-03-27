// // Dark Mode
// // document.addEventListener("DOMContentLoaded", function () {
// //   const body = document.body;
// //   const toggle = document.getElementById("darkToggle");
// //   const currentMode = localStorage.getItem("darkMode");

// //   if (currentMode === "enabled") {
// //     body.classList.add("dark-mode");
// //   }

// //   if (toggle) {
// //     toggle.addEventListener("click", () => {
// //       body.classList.toggle("dark-mode");

// //       if (body.classList.contains("dark-mode")) {
// //         localStorage.setItem("darkMode", "enabled");
// //       } else {
// //         localStorage.setItem("darkMode", "disabled");
// //       }
// //     });
// //   }
// // });

// //darkmode across all pages
// document.addEventListener("DOMContentLoaded", function () {
//   const body = document.body;
//   const toggle = document.getElementById("darkToggle");
//   const currentMode = localStorage.getItem("darkMode");

//   if (currentMode === "enabled") {
//     body.classList.add("dark-mode");
//     if (toggle) {
//       toggle.checked = true; // Ensure the toggle reflects the current mode
//     }
//   }

//   if (toggle) {
//     toggle.addEventListener("change", () => {
//       body.classList.toggle("dark-mode");

//       if (body.classList.contains("dark-mode")) {
//         localStorage.setItem("darkMode", "enabled");
//       } else {
//         localStorage.setItem("darkMode", "disabled");
//       }
//     });
//   }
// });

// // --- Dark Mode Toggle ---
// const toggleBtn = document.getElementById("darkToggle");
// const body = document.body;

// // Load from localStorage
// if (localStorage.getItem("darkMode") === "enabled") {
//   body.classList.add("dark");
//   toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
// }

// toggleBtn.addEventListener("click", () => {
//   body.classList.toggle("dark");
//   if (body.classList.contains("dark")) {
//     localStorage.setItem("darkMode", "enabled");
//     toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
//   } else {
//     localStorage.setItem("darkMode", "disabled");
//     toggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
//   }
// });

// document.addEventListener("DOMContentLoaded", () => {
//   const body = document.body;
//   const toggleBtn = document.getElementById("darkToggle");
//   const STORAGE_KEY = "darkMode";

//   // Load mode on every page
//   if (localStorage.getItem(STORAGE_KEY) === "enabled") {
//     body.classList.add("dark-mode");
//   }

//   // Only run toggle if button exists (settings page)
//   if (toggleBtn) {
//     const updateIcon = () => {
//       toggleBtn.innerHTML = body.classList.contains("dark-mode")
//         ? '<i class="fas fa-sun"></i>'
//         : '<i class="fas fa-moon"></i>';
//     };

//     updateIcon();

//     toggleBtn.addEventListener("click", () => {
//       body.classList.toggle("dark-mode");
//       if (body.classList.contains("dark-mode")) {
//         localStorage.setItem(STORAGE_KEY, "enabled");
//       } else {
//         localStorage.setItem(STORAGE_KEY, "disabled");
//       }
//       updateIcon();
//     });
//   }
// });

document.addEventListener("DOMContentLoaded", () => {
  const toggleBtn = document.getElementById("darkToggle");
  const body = document.body;

  if (localStorage.getItem("darkMode") === "enabled") {
    body.classList.add("dark");
    toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
  }

  toggleBtn.addEventListener("click", () => {
    body.classList.toggle("dark");
    if (body.classList.contains("dark")) {
      localStorage.setItem("darkMode", "enabled");
      toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
    } else {
      localStorage.setItem("darkMode", "disabled");
      toggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
    }
  });
});
