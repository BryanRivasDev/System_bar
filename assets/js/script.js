// Global UI helpers
// Hide navbar on scroll down, show on scroll up (simple behavior)
(function(){
	var lastScroll = 0;
	var navbar = document.querySelector('.navbar-custom');
	if(!navbar) return;
	var tolerance = 10;
	window.addEventListener('scroll', function(){
		var current = window.pageYOffset || document.documentElement.scrollTop;
		if (Math.abs(current - lastScroll) <= tolerance) return;
		if (current > lastScroll && current > 100) {
			// scroll down
			navbar.style.transform = 'translateY(-110%)';
			navbar.style.transition = 'transform 220ms ease-in-out';
		} else {
			// scroll up
			navbar.style.transform = 'translateY(0)';
			navbar.style.transition = 'transform 220ms ease-in-out';
		}
		lastScroll = current;
	}, {passive:true});
})();

// Small helper to keep footer scripts spacing consistent
document.addEventListener('DOMContentLoaded', function(){
	var fs = document.querySelector('.footer-scripts');
	if(!fs){
		fs = document.createElement('div');
		fs.className = 'footer-scripts';
		document.body.appendChild(fs);
	}
});

