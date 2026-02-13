/** 
 * router.js - Handles dynamic lesson type selection 
 * Loads the correct lesson JavaScript file dynamically 
 */

document.addEventListener("DOMContentLoaded", function () {
    console.log("🚀 Router Initialized - Waiting for Lesson Selection");

    // Attach event listener to detect lesson type selection
    document.addEventListener("lessonTypeSelected", function (event) {
        const lessonType = event.detail;
        loadLessonScript(lessonType);
    });
});

/**
 * Dynamically load the correct lesson script based on the selected type
 */
function loadLessonScript(lessonType) {
    let scriptPath = "";

    switch (lessonType) {
        case "flight":
            scriptPath = "assets/js/flight.js";
            break;
        case "ground":
            scriptPath = "assets/js/ground.js";
            break;
        case "simulator":
            scriptPath = "assets/js/simulator.js";
            break;
        case "rental":
            scriptPath = "assets/js/rental.js";
            break;
        case "review":
            scriptPath = "assets/js/review.js";
            break;
        default:
            console.error("❌ Invalid lesson type:", lessonType);
            return;
    }

    // Remove any previously loaded lesson script
    let existingScript = document.getElementById("dynamic-lesson-script");
    if (existingScript) {
        existingScript.remove();
    }

    // Create a new script tag for the selected lesson
    let script = document.createElement("script");
    script.src = scriptPath;
    script.id = "dynamic-lesson-script";
    script.onload = () => console.log(`✅ ${lessonType}.js loaded successfully`);
    script.onerror = () => console.error(`❌ Failed to load ${lessonType}.js`);
    
    document.body.appendChild(script);
}
