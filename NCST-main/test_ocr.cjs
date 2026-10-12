const Tesseract = require('tesseract.js');

async function testOCR() {
    console.log("Starting OCR...");
    const result = await Tesseract.recognize(
        'C:\\Users\\Luis\\.gemini\\antigravity-ide\\brain\\535fa2ad-a1d5-46ca-97a7-22b175d55032\\.user_uploaded\\media_1790603046686.jpg',
        'eng',
        { logger: m => console.log(m.status) }
    );
    console.log("\n--- RAW TEXT ---");
    console.log(result.data.text);
    
    console.log("\n--- NORMALIZED TEXT ---");
    const normalizedText = result.data.text.toUpperCase().replace(/[^A-Z0-9]/g, '');
    console.log(normalizedText);
    
    const fuzzyIncludes = (source, target, maxErrors = 2) => {
        if (!target) return false;
        for (let i = 0; i <= source.length - target.length; i++) {
            let errors = 0;
            for (let j = 0; j < target.length; j++) {
                if (source[i + j] !== target[j]) {
                    errors++;
                    if (errors > maxErrors) break;
                }
            }
            if (errors <= maxErrors) return true;
        }
        return false;
    };
    
    console.log("\n--- TESTS ---");
    console.log("hasNCST:", fuzzyIncludes(normalizedText, 'NCST', 1));
    console.log("hasNATIONALCOLLEGEOFSCIENCETECHNOLOGY:", fuzzyIncludes(normalizedText, 'NATIONALCOLLEGEOFSCIENCETECHNOLOGY', 4));
    console.log("hasNATIONALCOLLEGEOFSCIENCE:", fuzzyIncludes(normalizedText, 'NATIONALCOLLEGEOFSCIENCE', 3));
    console.log("hasNATIONALCOLLEGEOFSCIENCEANDTECHNOLOGY:", fuzzyIncludes(normalizedText, 'NATIONALCOLLEGEOFSCIENCEANDTECHNOLOGY', 5));
}

testOCR();
