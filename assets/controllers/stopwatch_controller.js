import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['elapsed'];

    disconnect() {
        clearInterval(this.ticker);
    }

    start() {
        const startedAt = performance.now();

        this.element.dataset.state = 'waiting';
        this.elapsedTarget.textContent = '0.0';
        this.ticker = setInterval(() => {
            this.elapsedTarget.textContent = ((performance.now() - startedAt) / 1000).toFixed(1);
        }, 100);
    }

    stop() {
        clearInterval(this.ticker);
        this.element.dataset.state = 'finished';
    }
}
