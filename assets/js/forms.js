// Document and Appointment Request Form Scripts

// File upload handling
function setupFileUpload() {
  const fileInputs = document.querySelectorAll('input[type="file"]');
  
  fileInputs.forEach(fileInput => {
    const dropZone = fileInput.parentElement.querySelector('.file-upload-zone');
    if (!dropZone) return;

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    // Highlight drop zone
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, () => {
        dropZone.classList.add('drag-over');
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, () => {
        dropZone.classList.remove('drag-over');
      });
    });

    // Handle drop
    dropZone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      const files = dt.files;
      fileInput.files = files;
      updateFileDisplay(fileInput);
    });

    // Handle file input change
    fileInput.addEventListener('change', () => {
      updateFileDisplay(fileInput);
    });
  });
}

// Update file display
function updateFileDisplay(fileInput) {
  const fileList = fileInput.parentElement.querySelector('.file-list');
  if (!fileList) return;

  fileList.innerHTML = '';
  
  if (fileInput.files.length === 0) {
    fileList.innerHTML = '<p class="no-files">No files selected</p>';
    return;
  }

  Array.from(fileInput.files).forEach((file, index) => {
    const fileItem = document.createElement('div');
    fileItem.className = 'file-item';
    fileItem.innerHTML = `
      <span>${file.name} (${(file.size / 1024).toFixed(2)} KB)</span>
      <button type="button" class="remove-file" onclick="removeFile(this, ${index})">Remove</button>
    `;
    fileList.appendChild(fileItem);
  });
}

// Remove file from selection
function removeFile(btn, index) {
  const fileInput = btn.closest('input[type="file"]') || btn.closest('.form-section').querySelector('input[type="file"]');
  const dataTransfer = new DataTransfer();
  
  Array.from(fileInput.files).forEach((file, i) => {
    if (i !== index) {
      dataTransfer.items.add(file);
    }
  });
  
  fileInput.files = dataTransfer.files;
  updateFileDisplay(fileInput);
}

// Validate form before submission
function validateDocumentRequestForm() {
  const docType = document.getElementById('docType').value;
  const purpose = document.getElementById('purpose').value.trim();
  const files = document.getElementById('supportingFiles').files;

  if (!docType) {
    showAlert('Please select a document type', 'danger');
    return false;
  }

  if (!purpose || purpose.length < 10) {
    showAlert('Please provide purpose (at least 10 characters)', 'danger');
    return false;
  }

  if (files.length === 0) {
    showAlert('Please upload at least one supporting file', 'danger');
    return false;
  }

  // Validate file types and sizes
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
  const maxSizeBytes = 5 * 1024 * 1024; // 5MB

  for (let file of files) {
    if (!allowedTypes.includes(file.type)) {
      showAlert(`Invalid file type: ${file.name}. Allowed: PDF, JPEG, PNG, DOC, DOCX`, 'danger');
      return false;
    }

    if (file.size > maxSizeBytes) {
      showAlert(`File too large: ${file.name}. Maximum size: 5MB`, 'danger');
      return false;
    }
  }

  return true;
}

// Validate appointment form
function validateAppointmentForm() {
  const bookingType = document.getElementById('bookingType').value;
  const bookingDate = document.getElementById('bookingDate').value;
  const bookingTime = document.getElementById('bookingTime').value;
  const purpose = document.getElementById('purpose').value.trim();

  if (!bookingType) {
    showAlert('Please select a booking type', 'danger');
    return false;
  }

  if (!bookingDate) {
    showAlert('Please select a booking date', 'danger');
    return false;
  }

  if (!bookingTime) {
    showAlert('Please select a booking time', 'danger');
    return false;
  }

  if (!purpose || purpose.length < 5) {
    showAlert('Please provide a purpose (at least 5 characters)', 'danger');
    return false;
  }

  // Validate date is not in past
  const selectedDate = new Date(bookingDate);
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(0, 0, 0, 0);

  if (selectedDate < tomorrow) {
    showAlert('Please select a future date', 'danger');
    return false;
  }

  return true;
}

// Setup booking type selection
function setupBookingTypeSelection() {
  const bookingTypeCards = document.querySelectorAll('.booking-type-card');
  
  bookingTypeCards.forEach(card => {
    card.addEventListener('click', () => {
      bookingTypeCards.forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      document.getElementById('bookingType').value = card.dataset.typeId;
    });
  });
}

// Setup document type selection
function setupDocumentTypeSelection() {
  const docTypeCards = document.querySelectorAll('.doc-type-card');
  
  docTypeCards.forEach(card => {
    card.addEventListener('click', () => {
      docTypeCards.forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      document.getElementById('docType').value = card.dataset.typeId;
    });
  });
}

// Generate time slot grid
function generateTimeSlots(date = null) {
  const timeContainer = document.getElementById('timeSlots');
  if (!timeContainer) return;

  timeContainer.innerHTML = '';
  const slots = [];

  for (let hour = 9; hour <= 16; hour++) {
    for (let minute = 0; minute < 60; minute += 30) {
      const timeStr = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
      slots.push(timeStr);
    }
  }

  slots.forEach(slot => {
    const slotBtn = document.createElement('button');
    slotBtn.type = 'button';
    slotBtn.className = 'time-slot';
    slotBtn.textContent = slot;
    slotBtn.addEventListener('click', (e) => {
      e.preventDefault();
      document.querySelectorAll('.time-slot').forEach(btn => btn.classList.remove('selected'));
      slotBtn.classList.add('selected');
      document.getElementById('bookingTime').value = slot;
    });
    timeContainer.appendChild(slotBtn);
  });
}

// Initialize forms on page load
document.addEventListener('DOMContentLoaded', () => {
  setupFileUpload();
  setupBookingTypeSelection();
  setupDocumentTypeSelection();
  
  const bookingDateInput = document.getElementById('bookingDate');
  if (bookingDateInput) {
    bookingDateInput.min = getMinDateForInput();
    bookingDateInput.addEventListener('change', () => {
      generateTimeSlots(bookingDateInput.value);
    });
    generateTimeSlots();
  }
});
