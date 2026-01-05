// Dashboard Scripts

// Update statistics (refresh every 30 seconds)
function updateStatistics() {
  fetch('/documentSystem/api/get-statistics.php')
    .then(response => response.json())
    .then(data => {
      document.getElementById('pendingAppointments').textContent = data.pending_appointments || 0;
      document.getElementById('pendingDocuments').textContent = data.pending_documents || 0;
      document.getElementById('readyDocuments').textContent = data.ready_documents || 0;
      document.getElementById('unreadNotifications').textContent = data.unread_notifications || 0;
    })
    .catch(error => console.error('Error updating statistics:', error));
}

// Load upcoming appointments
function loadUpcomingAppointments() {
  const container = document.getElementById('upcomingAppointments');
  if (!container) return;

  fetch('/documentSystem/api/get-appointments.php?limit=5&status=upcoming')
    .then(response => response.json())
    .then(data => {
      if (!data.appointments || data.appointments.length === 0) {
        container.innerHTML = '<p>No upcoming appointments</p>';
        return;
      }

      container.innerHTML = data.appointments.map(apt => `
        <div class="appointment-item">
          <div class="item-title">${apt.booking_type_name}</div>
          <div class="item-subtitle">${new Date(apt.booking_date).toLocaleDateString()} at ${apt.booking_time}</div>
          <span class="status-badge badge-pending">
            <i class="bi bi-hourglass-split"></i> Pending
          </span>
        </div>
      `).join('');
    })
    .catch(error => {
      container.innerHTML = '<p>Error loading appointments</p>';
      console.error('Error:', error);
    });
}

// Load pending documents
function loadPendingDocuments() {
  const container = document.getElementById('pendingDocuments');
  if (!container) return;

  fetch('/documentSystem/api/get-documents.php?status=pending&limit=3')
    .then(response => response.json())
    .then(data => {
      if (!data.documents || data.documents.length === 0) {
        container.innerHTML = '<p>No pending documents</p>';
        return;
      }

      container.innerHTML = data.documents.map(doc => `
        <div class="doc-item">
          <div class="item-title">${doc.document_type_name}</div>
          <div class="item-subtitle">Requested: ${new Date(doc.request_date).toLocaleDateString()}</div>
          <span class="status-badge badge-processing">
            <i class="bi bi-hourglass-split"></i> Processing
          </span>
        </div>
      `).join('');
    })
    .catch(error => {
      container.innerHTML = '<p>Error loading documents</p>';
      console.error('Error:', error);
    });
}

// Load ready documents
function loadReadyDocuments() {
  const container = document.getElementById('readyDocuments');
  if (!container) return;

  fetch('/documentSystem/api/get-documents.php?status=ready&limit=3')
    .then(response => response.json())
    .then(data => {
      if (!data.documents || data.documents.length === 0) {
        container.innerHTML = '<p>No ready documents</p>';
        return;
      }

      container.innerHTML = data.documents.map(doc => `
        <div class="doc-item ready">
          <div class="item-title">${doc.document_type_name}</div>
          <div class="item-subtitle">Ready since: ${new Date(doc.ready_date).toLocaleDateString()}</div>
          <span class="status-badge badge-ready">
            <i class="bi bi-check-circle"></i> Ready for Pickup
          </span>
        </div>
      `).join('');
    })
    .catch(error => {
      container.innerHTML = '<p>Error loading documents</p>';
      console.error('Error:', error);
    });
}

// Load recent notifications
function loadNotifications() {
  const container = document.getElementById('recentNotifications');
  if (!container) return;

  fetch('/documentSystem/api/get-notifications.php?limit=5')
    .then(response => response.json())
    .then(data => {
      if (!data.notifications || data.notifications.length === 0) {
        container.innerHTML = '<p>No notifications</p>';
        return;
      }

      container.innerHTML = data.notifications.map(notif => `
        <div class="notification-item ${notif.is_read ? 'read' : 'unread'}">
          <div>${notif.message}</div>
          <small>${new Date(notif.created_at).toLocaleDateString()}</small>
        </div>
      `).join('');
    })
    .catch(error => {
      container.innerHTML = '<p>Error loading notifications</p>';
      console.error('Error:', error);
    });
}

// Quick action handlers
function goToNewAppointment() {
  window.location.href = '/documentSystem/client/new-appointment.php';
}

function goToRequestDocument() {
  window.location.href = '/documentSystem/client/request-documents.php';
}

function goToViewAppointments() {
  window.location.href = '/documentSystem/client/view-appointments.php';
}

function goToViewDocuments() {
  window.location.href = '/documentSystem/client/view-documents.php';
}

// Load payment history
function loadPaymentHistory() {
  const container = document.getElementById('paymentHistory');
  if (!container) return;

  fetch('/documentSystem/api/get-payments.php?limit=5')
    .then(response => response.json())
    .then(data => {
      if (!data.payments || data.payments.length === 0) {
        container.innerHTML = '<p>No payments</p>';
        return;
      }

      container.innerHTML = data.payments.map(payment => `
        <tr>
          <td>${payment.reference_number}</td>
          <td>₱${parseFloat(payment.amount).toFixed(2)}</td>
          <td><span class="status-badge badge-${payment.status.toLowerCase()}">${payment.status}</span></td>
          <td>${new Date(payment.created_at).toLocaleDateString()}</td>
        </tr>
      `).join('');
    })
    .catch(error => {
      container.innerHTML = '<tr><td colspan="4">Error loading payments</td></tr>';
      console.error('Error:', error);
    });
}

// Initialize dashboard on load
document.addEventListener('DOMContentLoaded', () => {
  updateStatistics();
  loadUpcomingAppointments();
  loadPendingDocuments();
  loadReadyDocuments();
  loadNotifications();
  loadPaymentHistory();

  // Refresh statistics every 30 seconds
  setInterval(updateStatistics, 30000);
});
