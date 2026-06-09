// assets/js/pos/barcode.js
// Listens for USB/Bluetooth barcode scanner inputs globally

let barcodeBuffer = '';
let barcodeTimeout = null;

document.addEventListener('keydown', (e) => {
    // Ignore if typing in an input field (except the search box, which we might want to handle differently)
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return;
    }

    if (e.key === 'Enter') {
        if (barcodeBuffer.length > 2) {
            handleBarcodeScan(barcodeBuffer);
        }
        barcodeBuffer = '';
        clearTimeout(barcodeTimeout);
    } else if (e.key.length === 1) { // Only printable characters
        barcodeBuffer += e.key;
        
        // Reset buffer if typing is too slow (human typing vs scanner)
        clearTimeout(barcodeTimeout);
        barcodeTimeout = setTimeout(() => {
            barcodeBuffer = '';
        }, 100); // 100ms timeout
    }
});

async function handleBarcodeScan(barcode) {
    console.log("Scanned Barcode:", barcode);
    
    // Check IndexedDB offline catalog first
    if (window.OfflineSync) {
        const product = await window.OfflineSync.getProductByBarcodeLocal(barcode);
        
        if (product) {
            playScanSound('success');
            window.Cart.addItemToCart(product);
        } else {
            playScanSound('error');
            alert(`Produit introuvable (Code-barre: ${barcode})`);
        }
    }
}

function playScanSound(type) {
    const audio = new Audio(`../../assets/sounds/scan-${type}.mp3`);
    audio.play().catch(e => console.warn('Audio play prevented', e));
}

window.handleBarcodeScan = handleBarcodeScan;
