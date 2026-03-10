import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

const revealElements = document.querySelectorAll('[data-reveal]');

if (revealElements.length > 0 && 'IntersectionObserver' in window) {
	const observer = new IntersectionObserver((entries, obs) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				entry.target.classList.add('is-visible');
				obs.unobserve(entry.target);
			}
		});
	}, {
		threshold: 0.12,
		rootMargin: '0px 0px -40px 0px',
	});

	revealElements.forEach((el) => {
		observer.observe(el);
	});
}
