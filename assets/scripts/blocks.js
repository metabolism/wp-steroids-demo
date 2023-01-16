window.adjustFontSize = function (){ document.documentElement.style.fontSize = Math.max(7, Math.min(10, 10/(120/((window.innerWidth-375)*0.055+56))))+'px' }
window.adjustFontSize();
