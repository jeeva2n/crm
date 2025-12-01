// script.js
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('documentForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        generateDocument();
    });
    
    // Set default date to today
    document.getElementById('approvalDate').valueAsDate = new Date();
    
    // Add first item on load
    addItem();
});

let itemCounter = 0;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const itemId = `item_${itemCounter++}`;
    
    const newItem = document.createElement('div');
    newItem.className = 'item-row';
    newItem.id = itemId;
    newItem.innerHTML = `
        <div class="item-inputs-row">
            <input type="text" placeholder="Description" class="item-description">
            <input type="text" placeholder="Material" class="item-material">
            <input type="number" placeholder="Qty" class="item-quantity" value="1">
        </div>
        
        <div class="dimension-section">
            <select class="dimension-type-select" onchange="handleDimensionTypeChange(this, '${itemId}')">
                <option value="">-- Select Dimension Type --</option>
                <option value="plate">Plate (L × W × T)</option>
                <option value="pipe">Pipe (L × OD × ID × T)</option>
                <option value="t-joint">T-Joint (L × W × T × H)</option>
                <option value="custom">Block/Custom</option>
            </select>
            
            <!-- Plate Dimensions -->
            <div class="dimension-inputs" data-type="plate">
                <div class="dimension-input-group">
                    <label>Length (L)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Width (W)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Thickness (T)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
            </div>
            
            <!-- Pipe Dimensions -->
            <div class="dimension-inputs" data-type="pipe">
                <div class="dimension-input-group">
                    <label>Length (L)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Outer Dia (OD)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Inner Dia (ID)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Thickness (T)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
            </div>
            
            <!-- T-Joint Dimensions -->
            <div class="dimension-inputs" data-type="t-joint">
                <div class="dimension-input-group">
                    <label>Length (L)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Width (W)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Thickness (T)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
                <div class="dimension-input-group">
                    <label>Height (H)</label>
                    <input type="number" step="0.01" placeholder="0.00" onchange="updateDimensionString('${itemId}')">
                </div>
            </div>
            
            <!-- Custom Dimensions -->
            <div class="dimension-inputs" data-type="custom">
                <div class="dimension-input-group" style="grid-column: 1 / -1;">
                    <label>Custom Dimensions</label>
                    <input type="text" placeholder="Enter custom dimensions" onchange="updateDimensionString('${itemId}')">
                </div>
            </div>
            
            <!-- Hidden input to store the final dimension string -->
            <input type="hidden" class="item-dimensions">
            
            <!-- Display the formatted dimensions -->
            <div class="dimension-result"></div>
        </div>
        
        <button type="button" class="remove-item" onclick="removeItem(this)">Remove</button>
    `;
    container.appendChild(newItem);
}

function handleDimensionTypeChange(selectElement, itemId) {
    const item = document.getElementById(itemId);
    const dimensionInputsContainers = item.querySelectorAll('.dimension-inputs');
    const selectedType = selectElement.value;
    const dimensionResult = item.querySelector('.dimension-result');
    
    // Hide all dimension input containers
    dimensionInputsContainers.forEach(container => {
        container.classList.remove('active');
    });
    
    // Show the selected type's inputs
    if (selectedType) {
        const activeContainer = item.querySelector(`.dimension-inputs[data-type="${selectedType}"]`);
        if (activeContainer) {
            activeContainer.classList.add('active');
        }
    }
    
    // Clear dimension result
    dimensionResult.textContent = '';
    
    // Clear the hidden dimension input
    const hiddenDimensionInput = item.querySelector('.item-dimensions');
    if (hiddenDimensionInput) {
        hiddenDimensionInput.value = '';
    }
}

function updateDimensionString(itemId) {
    const item = document.getElementById(itemId);
    const selectedType = item.querySelector('.dimension-type-select').value;
    const dimensionResult = item.querySelector('.dimension-result');
    const hiddenDimensionInput = item.querySelector('.item-dimensions');
    
    if (!selectedType) {
        dimensionResult.textContent = '';
        hiddenDimensionInput.value = '';
        return;
    }
    
    let dimensionString = '';
    const activeContainer = item.querySelector(`.dimension-inputs[data-type="${selectedType}"].active`);
    
    if (activeContainer) {
        const inputs = activeContainer.querySelectorAll('input[type="number"]');
        const values = Array.from(inputs).map(input => input.value || '0');
        
        switch(selectedType) {
            case 'plate':
                if (values[0] && values[1] && values[2]) {
                    dimensionString = `${values[0]} × ${values[1]} × ${values[2]}`;
                }
                break;
            case 'pipe':
                if (values[0] && values[1] && values[2] && values[3]) {
                    dimensionString = `L: ${values[0]} × OD: ${values[1]} × ID: ${values[2]} × T: ${values[3]}`;
                }
                break;
            case 't-joint':
                if (values[0] && values[1] && values[2] && values[3]) {
                    dimensionString = `${values[0]} × ${values[1]} × ${values[2]} × ${values[3]}`;
                }
                break;
            case 'custom':
                const customInput = activeContainer.querySelector('input[type="text"]');
                dimensionString = customInput.value;
                break;
        }
    }
    
    dimensionResult.textContent = dimensionString || 'Please fill in all dimensions';
    hiddenDimensionInput.value = dimensionString;
}

function removeItem(button) {
    const itemsContainer = document.getElementById('itemsContainer');
    if (itemsContainer.children.length > 1) {
        button.parentElement.remove();
    } else {
        alert('At least one item is required');
    }
}

function generateDocument() {
    // Get form values
    const formType = document.getElementById('formType').value;
    const orderNumber = document.getElementById('orderNumber').value;
    const vendor = document.getElementById('vendor').value;
    const approvedBy = document.getElementById('approvedBy').value;
    const approvalDate = document.getElementById('approvalDate').value;
    const purchasedFrom = document.getElementById('purchasedFrom').value;
    const purchasedBy = document.getElementById('purchasedBy').value;
    const purchaseDate = document.getElementById('purchaseDate').value;
    
    // Update template
    document.getElementById('documentTitle').textContent = formType;
    document.getElementById('orderInfo').textContent = `ORDER NO: ${orderNumber}`;
    document.getElementById('vendorInfo').textContent = `VENDOR - ${vendor}`;
    document.getElementById('approvedByField').textContent = approvedBy;
    document.getElementById('approvalDateField').textContent = formatDate(approvalDate);
    document.getElementById('purchasedFromField').textContent = purchasedFrom;
    document.getElementById('purchasedByField').textContent = purchasedBy;
    document.getElementById('purchaseDateField').textContent = formatDate(purchaseDate);
    
    // Populate items table
    const tbody = document.getElementById('itemsTableBody');
    tbody.innerHTML = '';
    
    const itemRows = document.querySelectorAll('.item-row');
    itemRows.forEach((row, index) => {
        const description = row.querySelector('.item-description').value;
        const material = row.querySelector('.item-material').value;
        const dimensions = row.querySelector('.item-dimensions').value; // This now contains the formatted dimension string
        const quantity = row.querySelector('.item-quantity').value;
        
        if (description || material || dimensions) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td>${description}</td>
                <td>${material}</td>
                <td>${dimensions}</td>
                <td>${quantity}</td>
            `;
            tbody.appendChild(tr);
        }
    });
    saveToCRM();

    // Show template, hide form
    document.getElementById('formSection').style.display = 'none';
    document.getElementById('templateSection').style.display = 'block';
}

function editDocument() {
    // Show form, hide template
    document.getElementById('formSection').style.display = 'block';
    document.getElementById('templateSection').style.display = 'none';
}

function printDocument() {
    window.print();
}

function downloadPDF() {
    const element = document.getElementById('templateSection');
    const opt = {
        margin:       0.5,
        filename:     'document.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    // Hide buttons during PDF generation
    const buttons = document.querySelector('.action-buttons');
    buttons.style.display = 'none';
    
    html2pdf().set(opt).from(element).save().then(() => {
        // Show buttons again
        buttons.style.display = 'block';
    });
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB');
}
function saveToCRM() {
    const formData = {
        form_type: document.getElementById('formType').value,
        order_number: document.getElementById('orderNumber').value,
        vendor: document.getElementById('vendor').value,

        approved_by: document.getElementById('approvedBy').value,
        approval_date: document.getElementById('approvalDate').value,

        purchased_from: document.getElementById('purchasedFrom').value,
        purchased_by: document.getElementById('purchasedBy').value,
        purchase_date: document.getElementById('purchaseDate').value,

        // Collect items from form
        items: Array.from(document.querySelectorAll('.item-row')).map((row) => ({
            description: row.querySelector('.item-description').value,
            material: row.querySelector('.item-material').value,
            dimensions: row.querySelector('.item-dimensions').value,
            quantity: row.querySelector('.item-quantity').value
        }))
    };

    fetch("save_form.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(formData)
    })
    .then(r => r.json())
    .then(res => console.log("Saved:", res));
}
