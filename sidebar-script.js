document.addEventListener("DOMContentLoaded", () => {
  const sidebarToggle = document.getElementById("sidebarToggle")
  const sidebarNav = document.getElementById("sidebarNav")
  const sidebarOverlay = document.getElementById("sidebarOverlay")
  const mainContent = document.getElementById("mainContent")

  // Toggle sidebar
  sidebarToggle.addEventListener("click", () => {
    sidebarNav.classList.toggle("expanded")

    // Only add expanded class to mainContent on desktop
    if (window.innerWidth >= 992) {
      mainContent.classList.toggle("expanded")
    }
  })

  // Close sidebar when clicking on overlay (mobile)
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", () => {
      sidebarNav.classList.remove("expanded")
    })
  }

  // Close sidebar when clicking on a nav link (mobile)
  const navLinks = document.querySelectorAll(".nav-link")
  navLinks.forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth < 992) {
        sidebarNav.classList.remove("expanded")
      }
    })
  })

  // Handle window resize
  window.addEventListener("resize", () => {
    if (window.innerWidth >= 992) {
      // Desktop behavior
      if (sidebarNav.classList.contains("expanded")) {
        mainContent.classList.add("expanded")
      } else {
        mainContent.classList.remove("expanded")
      }
    } else {
      // Mobile behavior
      mainContent.classList.remove("expanded")
    }
  })
})
