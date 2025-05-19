// Theme toggle functionality
document.addEventListener("DOMContentLoaded", () => {
  // Check for saved theme preference or use default light theme
  const currentTheme = localStorage.getItem("theme") || document.documentElement.getAttribute("data-theme") || "light"
  document.documentElement.setAttribute("data-theme", currentTheme)

  // Update toggle button appearance based on current theme
  updateThemeToggle(currentTheme)

  // Add event listener to theme toggle button
  const themeToggle = document.getElementById("themeToggle")
  if (themeToggle) {
    themeToggle.addEventListener("click", () => {
      // Get current theme and toggle it
      const currentTheme = document.documentElement.getAttribute("data-theme")
      const newTheme = currentTheme === "dark" ? "light" : "dark"

      // Update HTML attribute and save preference
      document.documentElement.setAttribute("data-theme", newTheme)
      localStorage.setItem("theme", newTheme)

      // Also update PHP session via fetch request
      fetch("update-theme.php?theme=" + newTheme, {
        method: "GET",
      }).then(() => {
        // Force page refresh to ensure all styles are properly applied
        window.location.reload();
      })
    })
  }
})

// Update the theme toggle button appearance
function updateThemeToggle(theme) {
  const themeIcon = document.getElementById("themeIcon")
  if (themeIcon) {
    if (theme === "dark") {
      themeIcon.classList.remove("fa-moon")
      themeIcon.classList.add("fa-sun")
    } else {
      themeIcon.classList.remove("fa-sun")
      themeIcon.classList.add("fa-moon")
    }
  }
}

// Update navbar appearance based on theme
function updateNavbarAppearance(theme) {
  const navbar = document.querySelector(".navbar-container")
  const sidebar = document.querySelector(".sidebar-nav")

  if (navbar && sidebar) {
    if (theme === "dark") {
      navbar.style.backgroundColor = "var(--dashboard-sidebar-bg)"
      navbar.style.color = "var(--dashboard-text-color)"
      sidebar.style.backgroundColor = "var(--dashboard-sidebar-bg)"
    } else {
      navbar.style.backgroundColor = "var(--dashboard-sidebar-bg)"
      navbar.style.color = "var(--dashboard-text-color)"
      sidebar.style.backgroundColor = "var(--dashboard-sidebar-bg)"
    }
  }
}

// Update Chart.js colors if present
function updateChartColors(theme) {
  if (window.Chart) {
    // Update all charts on the page
    Chart.instances.forEach(chart => {
      if (theme === "dark") {
        chart.options.scales.x.grid.color = "rgba(255, 255, 255, 0.1)";
        chart.options.scales.y.grid.color = "rgba(255, 255, 255, 0.1)";
        chart.options.scales.x.ticks.color = "#efdbbf";
        chart.options.scales.y.ticks.color = "#efdbbf";
        chart.options.plugins.legend.labels.color = "#efdbbf";
      } else {
        chart.options.scales.x.grid.color = "rgba(0, 0, 0, 0.1)";
        chart.options.scales.y.grid.color = "rgba(0, 0, 0, 0.1)";
        chart.options.scales.x.ticks.color = "#212529";
        chart.options.scales.y.ticks.color = "#212529";
        chart.options.plugins.legend.labels.color = "#212529";
      }
      chart.update();
    });
  }
}

// Initialize theme on page load
window.addEventListener("load", () => {
  const currentTheme = document.documentElement.getAttribute("data-theme")
  updateThemeToggle(currentTheme)
  updateNavbarAppearance(currentTheme)
  updateChartColors(currentTheme)
})