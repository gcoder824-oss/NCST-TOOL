const Tesseract = require('tesseract.js');

async function testOCR() {
    console.log("Starting OCR with PSM 11...");
    const result11 = await Tesseract.recognize(
        'C:\\Users\\Luis\\.gemini\\antigravity-ide\\brain\\535fa2ad-a1d5-46ca-97a7-22b175d55032\\.user_uploaded\\media_1790603046686.jpg',
        'eng',
        { 
            tessedit_pageseg_mode: Tesseract.PSM.SPARSE_TEXT
        }
    );
    console.log("\n--- PSM 11 RAW TEXT ---");
    console.log(result11.data.text);
    
    console.log("Starting OCR with PSM 6...");
    const result6 = await Tesseract.recognize(
        'C:\\Users\\Luis\\.gemini\\antigravity-ide\\brain\\535fa2ad-a1d5-46ca-97a7-22b175d55032\\.user_uploaded\\media_1790603046686.jpg',
        'eng',
        { 
            tessedit_pageseg_mode: Tesseract.PSM.SINGLE_BLOCK
        }
    );
    console.log("\n--- PSM 6 RAW TEXT ---");
    console.log(result6.data.text);
}

testOCR();
