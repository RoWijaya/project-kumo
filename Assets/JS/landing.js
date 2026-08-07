document.addEventListener("mousemove", (e) => {
  const x = (e.clientX / window.innerWidth - 0.5) * 12;
  const y = (e.clientY / window.innerHeight - 0.5) * 12;

  document.body.style.backgroundPosition = `${50 + x / 2}% ${10 + y / 2}%`;
});
