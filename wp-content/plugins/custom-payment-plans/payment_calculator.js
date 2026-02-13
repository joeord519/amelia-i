console.log("✅ payment_calculator.js is loaded and running!");

document.addEventListener("DOMContentLoaded", function () {
    // Automatically launch calculator when page loads
    startPaymentPlanCalculator();
});

async function startPaymentPlanCalculator() {
    let trainingPath = await new Promise((resolve) => {
        Swal.fire({
            title: "What kind of interest do you have in Flight Training?",
            html: `
                <div class="swal-btn-container">
                    <button id="careerPilot" class="swal-btn">I want to be a Professional Pilot!</button>
                    <button id="hobbyPilot" class="swal-btn">I Want to Learn to Fly for FUN!</button>
                    <button id="notSure" class="swal-btn">Maybe Professional, Not Sure, but want to Learn to Fly</button>
                </div>
            `,
            showCancelButton: true,
            showConfirmButton: false,
            didOpen: () => {
                console.log("✅ SweetAlert2 is Open");
                document.getElementById("careerPilot").addEventListener("click", () => resolve("career"));
                document.getElementById("hobbyPilot").addEventListener("click", () => resolve("hobby"));
                document.getElementById("notSure").addEventListener("click", () => resolve("maybe"));
            }
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) {
                console.log("❌ User cancelled at the first question");
                resolve(null);
            }
        });
    });

    if (!trainingPath) return;
    console.log("✅ Training Path Selected:", trainingPath);
    handleTrainingSelection(trainingPath);
}

async function handleTrainingSelection(trainingPath) {
    console.log("🚀 handleTrainingSelection() called with:", trainingPath);

    let programOptions = {};

    if (trainingPath === "hobby") {
        console.log("✅ User selected 'Learn to Fly for FUN!'");
        programOptions = {
            "ppl": "Private Pilot License (PPL) - $16,500.00",
            "sport": "Sport Pilot - $8,800.00",
            "ifr": "Instrument Flight Rating (IFR) - $9,800.00",
            "multi": "Multi-Engine Add-on - $5,000.00"
        };
    } else if (trainingPath === "career") {
        console.log("✅ User selected 'Career-Oriented Training'");
        programOptions = {
            "zeroToHero": "Zero to Hero (Private, Commercial, CFI) - $75,000.00",
            "cfi": "Certified Flight Instructor (CFI) - $5,000.00"
        };
    } else if (trainingPath === "maybe") {
        console.log("✅ User selected 'Maybe Professional, Not Sure'");
        programOptions = {
            "ppl": "Private Pilot License (PPL) - $16,500.00",
            "sport": "Sport Pilot - $8,800.00",
            "ifr": "Instrument Flight Rating (IFR) - $9,800.00",
            "multi": "Multi-Engine Add-on - $5,000.00",
            "zeroToHero": "Zero to Hero (Private, Commercial, CFI) - $75,000.00"
        };
    }

    console.log("📌 Program Options:", programOptions);

    let { value: selectedProgram } = await Swal.fire({
        title: "Select Your Flight Training Program",
        input: "select",
        inputOptions: programOptions,
        inputPlaceholder: "Choose a program...",
        showCancelButton: true
    });

    if (!selectedProgram) {
        console.log("❌ User cancelled program selection");
        return;
    }

    console.log("✅ Program selected:", selectedProgram);
    startFinancingCalculation(selectedProgram);
}

async function collectLeadInfo() {
    let leadInfo = await Swal.fire({
        title: "Let's Get to Know You",
        html: `
            <input type="text" id="swal-first-name" class="swal2-input" placeholder="First Name">
            <input type="text" id="swal-last-name" class="swal2-input" placeholder="Last Name">
            <input type="text" id="swal-phone" class="swal2-input" placeholder="Phone Number">
        `,
        showCancelButton: true,
        confirmButtonText: "Next",
        preConfirm: () => {
            return {
                firstName: document.getElementById("swal-first-name").value.trim(),
                lastName: document.getElementById("swal-last-name").value.trim(),
                phone: document.getElementById("swal-phone").value.trim(),
            };
        }
    });

    if (!leadInfo || !leadInfo.firstName || !leadInfo.lastName || !leadInfo.phone) {
        Swal.fire("Error", "All fields are required.", "error");
        return collectLeadInfo();
    }

    console.log("📩 Lead Info Collected:", leadInfo);
    return leadInfo;
}

async function startFinancingCalculation(selectedProgram) {
    console.log("🚀 startFinancingCalculation() called with:", selectedProgram);

    let leadInfo = await collectLeadInfo();
    if (!leadInfo) return;

    let financingOptions = "";
    let downPayment = 0;

    try {
        if (selectedProgram === "ppl") {
            console.log("✅ PPL Financing Flow Started");

            let pplPaymentPlan = await new Promise((resolve) => {
                Swal.fire({
                    title: "How would you like to pay for Private Pilot Training?",
                    html: `
                        <div class="swal-btn-container">
                            <button id="fullPay" class="swal-btn">Full Pay - $15,500 (Save $1,000!)</button>
                            <button id="phasePay" class="swal-btn">Phase Pay - $4,000 Down, then 7 payments</button>
                            <button id="customPay" class="swal-btn">Name Your Down Payment</button>
                        </div>
                    `,
                    showCancelButton: true,
                    showConfirmButton: false,
                    didOpen: () => {
                        document.getElementById("fullPay").addEventListener("click", () => resolve("full"));
                        document.getElementById("phasePay").addEventListener("click", () => resolve("phase"));
                        document.getElementById("customPay").addEventListener("click", () => resolve("custom"));
                    }
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        console.log("❌ User Cancelled PPL Financing Selection");
                        resolve(null);
                    }
                });
            });

            if (!pplPaymentPlan) return;

            if (pplPaymentPlan === "custom") {
                let { value: customDownPayment } = await Swal.fire({
                    title: "Enter Your Down Payment",
                    input: "number",
                    inputPlaceholder: "Minimum $5,000",
                    inputAttributes: { min: "5000", step: "100" },
                    showCancelButton: true
                });

                if (!customDownPayment) return;
                financingOptions = `Custom Down Payment: $${customDownPayment}`;
                downPayment = customDownPayment;
            } else if (pplPaymentPlan === "full") {
                financingOptions = "Full Pay - $15,500";
                downPayment = 15500;
            } else {
                financingOptions = "Phase Pay - $4,000 Down, then 7 payments";
                downPayment = 4000;
            }
        }

        await Swal.fire({
            title: "Financing Summary",
            html: `<p>You selected <strong>${selectedProgram.toUpperCase()}</strong></p>
                   <p><strong>Payment Plan:</strong> ${financingOptions}</p>`,
            icon: "info",
            confirmButtonText: "Proceed"
        });

        console.log("✅ Proceeding to Submit Lead");
        submitLead(selectedProgram, financingOptions, leadInfo, downPayment);
    } catch (error) {
        console.error("🚨 Error in startFinancingCalculation():", error);
        Swal.fire("Error", "Something went wrong. Please refresh the page and try again.", "error");
    }
}

async function submitLead(program, financingPlan, leadInfo, downPayment) {
    console.log("🚀 submitLead() called with:", { program, financingPlan, leadInfo });

    if (!program || !financingPlan || !leadInfo) {
        console.error("❌ Missing required parameters in submitLead:", { program, financingPlan, leadInfo });
        Swal.fire("Error", "Something went wrong with the financing selection. Please try again.", "error");
        return;
    }

    let { value: email } = await Swal.fire({
        title: "Enter Your Email",
        input: "email",
        inputPlaceholder: "Enter your email to receive your financing plan",
        showCancelButton: true,
        confirmButtonText: "Submit",
        inputValidator: (value) => {
            if (!value) {
                return "Email is required!";
            }
        }
    });

    if (!email) return;

    let monthlyPayment = financingPlan.includes("7 payments") ? ((16500 - downPayment) / 7).toFixed(2) : 0;
    let cosigner = "No"; // Default value for now

    let leadData = {
        first_name: leadInfo.firstName,
        last_name: leadInfo.lastName,
        email: email,
        phone: leadInfo.phone,
        training_program: program,
        payment_plan: financingPlan,
        down_payment: downPayment,
        monthly_payment: monthlyPayment,
        cosigner: cosigner
    };

    console.log("📩 Submitting lead data:", leadData);

    let response = await fetch("submit_payment_plan.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(leadData)
    });

    let result = await response.json();
    console.log("📄 Lead Submission Response:", result);

    if (result.status === "success") {
        generatePDF(leadData);
    } else {
        Swal.fire("Error", "Failed to submit financing application. Please try again.", "error");
    }
}
