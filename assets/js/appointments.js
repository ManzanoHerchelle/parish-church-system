// Appointments and Documents View Scripts

// Tab functionality
function setupTabs() {
  const tabBtns = document.querySelectorAll('.tab-btn');
  
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      // Remove active from all buttons and contents
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      
      // Add active to clicked button and corresponding content
      btn.classList.add('active');
      const tabId = btn.getAttribute('data-tab');
      const tabContent = document.getElementById(tabId);
      if (tabContent) {
        tabContent.classList.add('active');
      }
    });
  });
}

// Cancel appointment
function openCancelModal(appointmentId) {
  console.log('Opening cancel modal for appointment:', appointmentId);
  const modal = document.getElementById('cancelModal');
  console.log('Modal element found:', modal);
  if (modal) {
    modal.classList.add('active');
    const inputElement = document.getElementById('cancel_booking_id');
    console.log('Input element found:', inputElement);
    if (inputElement) {
      inputElement.value = appointmentId;
    }
  } else {
    console.error('cancelModal element not found!');
  }
}

// Submit cancellation
function submitCancellation() {
  const appointmentId = document.getElementById('cancel_booking_id').value;
  const reason = document.getElementById('cancellationReason')?.value || '';

  if (!appointmentId) {
    showAlert('Invalid appointment', 'danger');
    return;
  }

  const formData = new FormData();
  formData.append('appointmentId', appointmentId);
  formData.append('reason', reason);
  formData.append('action', 'cancelAppointment');

  fetch(window.location.pathname, {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    showAlert('Appointment cancelled successfully', 'success');
    closeModal('cancelModal');
    setTimeout(() => window.location.reload(), 1500);
  })
  .catch(error => {
    showAlert('Error cancelling appointment', 'danger');
    console.error('Error:', error);
  });
}

// Close cancellation modal
function closeCancelModal() {
  const modal = document.getElementById('cancelModal');
  if (modal) {
    modal.classList.remove('active');
    document.getElementById('cancel_booking_id').value = '';
  }
}

// Reschedule appointment
function rescheduleAppointment(appointmentId) {
  const modal = document.getElementById('rescheduleModal');
  if (modal) {
    modal.classList.add('active');
    document.getElementById('reschedule_booking_id').value = appointmentId;
    
    // Fetch booked dates and times
    fetchBookedSlots(appointmentId);
  }
}

// Fetch booked slots from backend
function fetchBookedSlots(appointmentId) {
  fetch(`/documentSystem/api/get-booked-slots.php?appointment_id=${appointmentId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Store booked slots globally for use in date/time validation
        window.bookedSlots = data.booked_slots || {};
        window.currentAppointmentId = appointmentId;
        
        // Disable booked dates
        disableBookedDates();
        
        // Add event listener for date changes
        const dateInput = document.getElementById('reschedule_date');
        if (dateInput) {
          dateInput.addEventListener('change', function() {
            disableBookedTimes(this.value);
          });
        }
      }
    })
    .catch(error => console.error('Error fetching booked slots:', error));
}

// Disable booked dates in the date picker
function disableBookedDates() {
  const dateInput = document.getElementById('reschedule_date');
  if (!dateInput) return;
  
  // Get all booked dates
  const bookedDates = Object.keys(window.bookedSlots || {});
  
  // Set min date to today
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  dateInput.min = `${year}-${month}-${day}`;
  
  // Add change listener to validate and disable times
  if (!dateInput.hasListener) {
    dateInput.addEventListener('change', function() {
      disableBookedTimes(this.value);
    });
    dateInput.hasListener = true;
  }
}

// Disable booked times for a specific date
function disableBookedTimes(selectedDate) {
  const timeInput = document.getElementById('reschedule_time');
  if (!timeInput) return;
  
  // Get booked times for this date
  const bookedTimes = (window.bookedSlots && window.bookedSlots[selectedDate]) ? 
    window.bookedSlots[selectedDate] : [];
  
  // Remove old data attributes
  timeInput.removeAttribute('disabled-times');
  
  // Store disabled times as data attribute for validation
  if (bookedTimes.length > 0) {
    timeInput.setAttribute('disabled-times', JSON.stringify(bookedTimes));
    
    // Add event listener for time change to show warning
    if (!timeInput.hasTimeListener) {
      timeInput.addEventListener('change', function() {
        const warning = document.getElementById('bookedTimesWarning');
        if (bookedTimes.includes(this.value)) {
          if (warning) warning.style.display = 'block';
        } else {
          if (warning) warning.style.display = 'none';
        }
      });
      timeInput.hasTimeListener = true;
    }
  } else {
    // Hide warning if no times are booked
    const warning = document.getElementById('bookedTimesWarning');
    if (warning) warning.style.display = 'none';
  }
  
  // Clear current time selection if it's booked
  if (timeInput.value && bookedTimes.includes(timeInput.value)) {
    timeInput.value = '';
    showAlert('Selected time is already booked. Please choose another time.', 'warning');
  }
}

// Submit reschedule
function submitReschedule() {
  const appointmentId = document.getElementById('reschedule_booking_id').value;
  const newDate = document.getElementById('reschedule_date').value;
  const newTime = document.getElementById('reschedule_time').value;
  const reason = document.getElementById('reschedule_reason')?.value || '';

  if (!appointmentId || !newDate || !newTime) {
    showAlert('Please fill in all required fields', 'danger');
    return;
  }

  // Validate that the selected time is not booked
  const disabledTimes = document.getElementById('reschedule_time').getAttribute('disabled-times');
  if (disabledTimes) {
    const bookedTimes = JSON.parse(disabledTimes);
    if (bookedTimes.includes(newTime)) {
      showAlert('This time is already booked. Please select a different time.', 'danger');
      return;
    }
  }

  const formData = new FormData();
  formData.append('appointment_id', appointmentId);
  formData.append('new_date', newDate);
  formData.append('new_time', newTime);
  formData.append('reason', reason);
  formData.append('action', 'reschedule_appointment');

  fetch(window.location.pathname, {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    showAlert('Appointment rescheduled successfully', 'success');
    closeRescheduleModal();
    setTimeout(() => window.location.reload(), 1500);
  })
  .catch(error => {
    showAlert('Error rescheduling appointment', 'danger');
    console.error('Error:', error);
  });
}

// Close reschedule modal
function closeRescheduleModal() {
  const modal = document.getElementById('rescheduleModal');
  if (modal) {
    modal.classList.remove('active');
    document.getElementById('reschedule_booking_id').value = '';
    document.getElementById('reschedule_date').value = '';
    document.getElementById('reschedule_time').value = '';
    document.getElementById('reschedule_reason').value = '';
  }
}

// View appointment details (placeholder for future implementation)
function viewAppointmentDetails(appointmentId) {
  showAlert('Details modal coming soon', 'info');
}

// Make payment for document
function makePayment(documentId) {
  showAlert('Payment system integration coming soon', 'info');
}

// Download document
function downloadDocument(documentId) {
  showAlert('Download feature coming soon', 'info');
}

// View document details
function viewDocumentDetails(documentId) {
  showAlert('Details modal coming soon', 'info');
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal')) {
    e.target.classList.remove('active');
  }
});

// Close modal when pressing Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal.active').forEach(modal => {
      modal.classList.remove('active');
    });
  }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  setupTabs();
});
