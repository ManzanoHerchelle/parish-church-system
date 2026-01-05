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
function cancelAppointment(appointmentId) {
  const modal = document.getElementById('cancelModal');
  if (modal) {
    modal.classList.add('active');
    document.getElementById('cancelAppointmentId').value = appointmentId;
  }
}

// Submit cancellation
function submitCancellation() {
  const appointmentId = document.getElementById('cancelAppointmentId').value;
  const reason = document.getElementById('cancellationReason').value;

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
    document.getElementById('cancellationReason').value = '';
  }
}

// Reschedule appointment (placeholder for future implementation)
function rescheduleAppointment(appointmentId) {
  showAlert('Rescheduling feature coming soon', 'info');
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
