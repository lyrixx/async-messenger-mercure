import { Controller } from '@hotwired/stimulus';

// A large CSV publishes thousands of events: keep the DOM small.
const MAX_LINES = 200;

export default class extends Controller {
    static targets = ['count', 'line', 'list'];

    received = 0;

    record({ target: stream }) {
        const line = this.lineTarget.content.cloneNode(true);

        line.querySelector('time').textContent = new Date().toLocaleTimeString('en-GB', { fractionalSecondDigits: 3, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        line.querySelector('.mm-log-action').textContent = `${stream.action} #${stream.target}`;
        line.querySelector('.mm-log-data').textContent = stream.templateContent.textContent.replace(/\s+/g, ' ').trim();

        this.listTarget.prepend(line);
        this.listTarget.querySelector(`li:nth-child(${MAX_LINES + 1})`)?.remove();

        this.received += 1;
        this.countTarget.textContent = this.received;
    }
}
