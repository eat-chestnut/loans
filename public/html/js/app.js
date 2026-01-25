document.addEventListener('DOMContentLoaded', () => {
    if (window.AppCalculator) {
        window.AppCalculator.initCalculator();
    }
    if (window.AppContract) {
        window.AppContract.initContractDownload();
    }
});
