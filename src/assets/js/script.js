// Ακορντεόν
document.querySelectorAll('.accordion-header').forEach(header => {
  header.addEventListener('click', () => {
    const content = header.nextElementSibling;
    const isVisible = content.style.display === 'block';
    document.querySelectorAll('.accordion-content').forEach(c => c.style.display = 'none');
    content.style.display = isVisible ? 'none' : 'block';
  });
});

// Theme toggle με cookie
const themeToggle = document.querySelector('.theme-toggle');
const currentTheme = document.cookie.split('; ').find(row => row.startsWith('theme='))?.split('=')[1];
if (currentTheme === 'light') document.body.classList.add('light');

themeToggle.addEventListener('click', () => {
  document.body.classList.toggle('light');
  const theme = document.body.classList.contains('light') ? 'light' : 'dark';
  document.cookie = `theme=${theme}; path=/; max-age=31536000`;
});

// Responsive menu
const toggleBtn = document.querySelector('.menu-toggle');
const menu = document.querySelector('.menu');
toggleBtn.addEventListener('click', () => {
  menu.classList.toggle('show');
});

// Scroll-triggered fade-in animation
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
    }
  });
}, {
  threshold: 0.1
});

document.querySelectorAll('.card').forEach(card => {
  observer.observe(card);
});

// Fade-in animation on page load
window.addEventListener('DOMContentLoaded', () => {
  document.querySelector('.container').classList.add('fade-in');
});

// Ενεργοποίηση πεδίου αναζήτησης όταν πατάει το εικονίδιο
const searchToggle = document.querySelector('.search-toggle');
const searchIcon = document.querySelector('.search-icon');
const searchInput = document.querySelector('.search-input');

searchIcon.addEventListener('click', () => {
  searchToggle.classList.toggle('active');
  if (searchToggle.classList.contains('active')) {
    searchInput.focus();
  }
});