import { Controller } from '@hotwired/stimulus';

const sizeFormatter = new Intl.NumberFormat('en', {
    maximumFractionDigits: 1,
    notation: 'compact',
    style: 'unit',
    unit: 'byte',
    unitDisplay: 'narrow',
});

export default class extends Controller {
    static targets = ['drop', 'fileName', 'input'];

    highlight() {
        this.dropTarget.classList.add('is-dragging');
    }

    pick() {
        const [file] = this.inputTarget.files;

        this.fileNameTarget.textContent = file
            ? `${file.name} (${sizeFormatter.format(file.size)})`
            : this.fileNameTarget.dataset.emptyLabel;
    }

    unhighlight() {
        this.dropTarget.classList.remove('is-dragging');
    }
}
