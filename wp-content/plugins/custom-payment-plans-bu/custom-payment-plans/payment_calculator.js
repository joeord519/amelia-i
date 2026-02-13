document.addEventListener("DOMContentLoaded", function() {
    // Automatically launch calculator when page loads
    startPaymentPlanCalculator();
});

async function startPaymentPlanCalculator() {
    let financingData = await fetchFinancingOptions();

    if (!financingData) {
        Swal.fire("Error", "Unable to load financing options. Please try again.", "error");
        return;
    }

    let { value: trainingProgram } = await Swal.fire({
        title: "Select Your Flight Training Program",
        input: "select",
        inputOptions: getTrainingPrograms(financingData),
        inputPlaceholder: "Choose a program...",
        showCancelButton: true
    });

    if (!trainingProgram) return;

    let { value: downPayment } = await Swal.fire({
        title: "How much can you put down?",
        input: "number",
        inputPlaceholder: "Enter down payment amount...",
        inputAttributes: { min: "0", step: "100" },
        showCancelButton: true
    });

    if (downPayment === undefined) return;

    let { value: monthlyBudget } = await Swal.fire({
        title: "What’s your max monthly payment?",
        input: "number",
        inputPlaceholder: "Enter max monthly amount...",
        inputAttributes: { min: "100", step: "50" },
        showCancelButton: true
    });

    if (monthlyBudget === undefined) return;

    let { value: creditScore } = await Swal.fire({
        title: "What’s your credit score?",
        input: "select",
        inputOptions: {
            "Excellent (750+)": "Excellent (750+)",
            "Good (700-749)": "Good (700-749)",
            "Fair (650-699)": "Fair (650-699)",
            "Poor (<650)": "Poor (<650)"
        },
        showCancelButton: true
    });

    if (!creditScore) return;

    let { value: cosigner } = await Swal.fire({
        title: "Do you have a co-signer?",
        input: "select",
        inputOptions: { "Yes": "Yes", "No": "No" },
        showCancelButton: true
    });

    if (!cosigner) return;

    let financingPlan = calculateBestPlan(financingData, trainingProgram, downPayment, monthlyBudget, creditScore, cosigner);

    await Swal.fire({
        title: "Recommended Payment Plan",
        html: financingPlan,
        showCancelButton: true,
        confirmButtonText: "Finalize Plan"
    });

    submitLead(trainingProgram, downPayment, monthlyBudget, creditScore, cosigner, financingPlan);
}

async function fetchFinancingOptions() {
    try {
        let response = await fetch("fetch_financing_options.php");
        let data = await response.json();
        console.log("Financing Data:", data); // Debugging log
        return data.status === "success" ? data.data : null;
    } catch (error) {
        console.error("Error fetching financing data:", error);
        return null;
    }
}

function getTrainingPrograms(data) {
    let options = {};
    data.forEach(plan => {
        options[plan.program_name] = plan.program_name + " - $" + plan.total_cost;
    });
    return options;
}

function calculateBestPlan(data, program, downPayment, monthlyBudget, creditScore, cosigner) {
    let selectedPlan = data.find(plan => plan.program_name === program);
    if (!selectedPlan) return "No available financing options for this program.";

    let totalCost = selectedPlan.total_cost;
    let remainingBalance = totalCost - downPayment;

    let monthlyPayment;
    let trainingSpeed;
    let financingType;

    if (remainingBalance <= 0) {
        // Fully paid upfront
        monthlyPayment = "No Financing Needed!";
        trainingSpeed = "Immediate Training Access";
        financingType = "Paid in Full";
    } else {
        if (selectedPlan.loan_available === "Yes" && (creditScore.includes("Excellent") || cosigner === "Yes")) {
            // Use Stratus Loan (Best for fast-track)
            let stratusTermMonths = 180; // Example: 15 years (180 months)
            let estimatedInterestRate = 0.16; // Example: 16%
            let monthlyInterest = estimatedInterestRate / 12;
            let loanAmount = remainingBalance * 1.15; // Stratus has a 15% origination fee
            monthlyPayment = ((loanAmount * monthlyInterest) / (1 - Math.pow(1 + monthlyInterest, -stratusTermMonths))).toFixed(2);
            trainingSpeed = "Fast-Track (12-24 months)";
            financingType = "Stratus Loan (Includes 15% Origination Fee)";
        } else {
            // Use Piston In-House Financing
            let maxMonths = JSON.parse(selectedPlan.piston_plan_terms)?.max_term || 48;
            monthlyPayment = (remainingBalance / maxMonths).toFixed(2);
            trainingSpeed = maxMonths > 12 ? "Throttled (Paced Training)" : "Standard Training Speed";
            financingType = "Piston In-House Financing";
        }
    }

    return `<strong>Total Cost:</strong> $${totalCost.toLocaleString()}<br>
            <strong>Down Payment:</strong> $${downPayment.toLocaleString()}<br>
            <strong>Remaining Balance:</strong> $${remainingBalance.toLocaleString()}<br>
            <strong>Estimated Monthly Payment:</strong> $${monthlyPayment}<br>
            <strong>Financing Type:</strong> ${financingType}<br>
            <strong>Training Speed:</strong> ${trainingSpeed}`;
}

async function submitLead(program, downPayment, monthlyBudget, creditScore, cosigner, financingPlan) {
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

    if (!email) return; // Stop if no email is entered

    let leadData = {
        first_name: "John", // Replace with dynamic data
        last_name: "Doe",
        email: email, // ✅ Collect Email
        phone: "555-1234",
        training_program: program,
        down_payment: downPayment,
        payment_plan: financingPlan.includes("Fast-Track") ? "Fast-Track" : "Long-Term",
        monthly_payment: parseFloat(financingPlan.match(/\$\d+\.\d+/)?.[0]?.replace("$", "")) || 0,
        prepaid_hours: 10,
        throttled_hours: 3,
        cosigner: cosigner
    };

    let response = await fetch("submit_payment_plan.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(leadData)
    });

    let result = await response.json();

    if (result.status === "success") {
        generatePDF(leadData);
    } else {
        Swal.fire("Error", "Failed to submit financing application. Please try again.", "error");
    }
}

async function generatePDF(leadData) {
    console.log("🚀 Sending Data to PDF Generator:", leadData);

    let response = await fetch("generate_pdf.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(leadData)
    });

    let result = await response.json();
    console.log("📄 PDF Generation Response:", result);

    if (result.status === "success") {
        Swal.fire({
            title: "Review Your Financing Plan",
            html: `
                <iframe id="pdfViewer" src="${result.pdf_url}#toolbar=0&view=FitH&navpanes=0" width="100%" height="500px" style="border: none;"></iframe>
                <br>
                <div style="text-align: center; margin-top: 10px;">
                    <button onclick="window.open('${result.pdf_url}', '_blank')" class="swal2-confirm swal2-styled" style="background: #4CAF50;">Download PDF</button>
                    <button onclick="document.getElementById('pdfViewer').contentWindow.print()" class="swal2-confirm swal2-styled" style="background: #2196F3;">Print PDF</button>
                </div>
                <br>
                <strong>Next Steps:</strong>
                <p>To proceed, choose one of the options below.</p>
            `,
            icon: "success",
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: "Continue to Enrollment",
            denyButtonText: "Apply for Stratus Financing",
            cancelButtonText: "Stay Here",
            width: "90%",  
            heightAuto: false  
        }).then((action) => {
            if (action.isConfirmed) {
                window.location.href = "https://amelia-i.com/enroll-now/";
            } else if (action.isDenied) {
                window.location.href = "https://apply.stratus.finance/pistonaviation8450001";
            }
        });
    } else {
        Swal.fire("Error", "Failed to generate PDF. Debug Info: " + JSON.stringify(result), "error");
    }
}








