let dashboardData = null;
// Retrieve the current user ID from localStorage. Fallback to 1 if not found.
let userId;
try {
    userId = localStorage.getItem('user_id');
} catch (e) {
    userId = null;
}
userId = userId || 1;

// Utility functions
function parseDollar(str) {
    return parseFloat(String(str).replace(/[^0-9.-]+/g, '')) || 0;
}

function formatDollar(num) {
    const hasDecimals = Number(num) % 1 !== 0;
    return Number(num).toLocaleString('en-US', {
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: hasDecimals ? 2 : 0
    }) + ' $';
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/'/g, '&#39;')
        .replace(/"/g, '&quot;');
}

function showBootstrapAlert(containerId, message, type = 'success') {
    const icons = {
        success: 'fa-check-circle',
        danger: 'fa-exclamation-triangle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    const icon = icons[type] || icons.info;
    const alertHtml = `
        <div class="alert alert-${escapeHtml(type)} alert-dismissible fade show" role="alert">
            <i class="fas ${icon} me-2"></i>${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
    $('#' + containerId).html(alertHtml);
}

const currencyNames = {
    btc: 'Bitcoin',
    bch: 'Bitcoin Cash',
    eth: 'Ethereum',
    ltc: 'Litecoin',
    usdt: 'Tether',
    usdc: 'USD Coin'
};

function renderWalletTable(wallets = dashboardData.personalData.wallets || []) {
    const $tbody = $('#walletTableBody');
    $tbody.empty();
    wallets.forEach(w => {
        const row = `<tr data-id="${escapeHtml(w.id)}">
                <td>${escapeHtml(currencyNames[w.currency] || w.currency)}</td>
                <td>${escapeHtml(w.network)}</td>
                <td class="wallet-address">${escapeHtml(w.address || '---')}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1 wallet-edit" data-id="${escapeHtml(w.id)}"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger wallet-delete" data-id="${escapeHtml(w.id)}"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        $tbody.append(row);
    });
}

async function fetchWallets() {
    try {
        const res = await fetch('get_wallets.php?user_id=' + encodeURIComponent(userId));
        const data = await res.json();
        dashboardData.personalData.wallets = data.wallets || [];
        renderWalletTable();
    } catch (err) {
        console.error('Failed to fetch wallet addresses', err);
    }
}

function updatePlatformBankDetails() {
    if (!dashboardData) return;
    const bw = dashboardData.bankWithdrawInfo || {};
    $('#widhrawbankname').text(bw.widhrawBankName || '---');
    $('#widhrawusername').text(bw.widhrawAccountName || '---');
    $('#widhrawacountnumber').text(bw.widhrawAccountNumber || '---');
    $('#widhrawiben').text(bw.widhrawIban || '---');
    $('#widhrawswift').text(bw.widhrawSwiftCode || '---');
}

async function fetchDashboardData() {
    try {
        const res = await fetch('getter.php?user_id=' + encodeURIComponent(userId));
        dashboardData = await res.json();
        if (dashboardData.personalData) {
            dashboardData.personalData.balance = parseDollar(dashboardData.personalData.balance);
            dashboardData.personalData.totalDepots = parseDollar(dashboardData.personalData.totalDepots);
            dashboardData.personalData.totalRetraits = parseDollar(dashboardData.personalData.totalRetraits);
            dashboardData.personalData.nbTransactions = parseInt(dashboardData.personalData.nbTransactions) || 0;
        }
        ['transactions','deposits','retraits'].forEach(t => {
            (dashboardData[t] || []).forEach(r => { r.amount = parseDollar(r.amount); });
        });
        (dashboardData.tradingHistory || []).forEach(r => {
            r.montant = parseDollar(r.montant);
            r.prix = parseDollar(r.prix);
            r.profitPerte = r.profitPerte === null || r.profitPerte === '-' ? null : parseFloat(r.profitPerte);
        });
        console.log("Fetched dashboard data", dashboardData);
        const steps = Object.values(dashboardData.defaultKYCStatus || {});
        const completed = steps.filter(s => String(s.status) === '1').length;
        const progress = Math.round((completed / steps.length) * 100);
        dashboardData.kycProgress = progress;
        const $bar = $('#kycProgressBar');
        const $label = $('#kycStatusLabel');
        if ($bar.length) {
            $bar.css('width', progress + '%').text(progress + '%');
        }
        if ($label.length) {
            $label.text(progress === 100 ? 'completed' : 'pending');
        }
        updatePlatformBankDetails();
        initializeUI();
    } catch (err) {
        console.error("Failed to load dashboard data", err);
        alert("Erreur : Impossible de charger les données utilisateur.");
    }
}

async function saveDashboardData() {
    try {
        const res = await fetch('setter.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...dashboardData, user_id: userId })
        });
        if (!res.ok) throw new Error("Failed to save data");
        const result = await res.json();
        console.log("Saved dashboard data", result);
    } catch (err) {
        console.error("Failed to save dashboard data", err);
        alert("Erreur : Impossible d'enregistrer les données utilisateur.");
    }
}

$(document).ready(async function () {
    await fetchDashboardData();
    await fetchWallets();
});

function initializeUI() {
    dashboardData.personalData.wallets = dashboardData.wallets || dashboardData.personalData.wallets || [];
    function updateBalances() {
        const bal = formatDollar(dashboardData.personalData.balance);
        $('#soldeTotal').text(bal);
        $('#soldeintrade').text(bal);
        $('#soldedisponible1').text(bal);
        $('#soldedisponible2').text(bal);
        $('#soldedisponible3').text(bal);
        $('#accountBalance').text(bal);
    }

    function updateCounters() {
        $('#totalDepots').text(formatDollar(dashboardData.personalData.totalDepots));
        $('#totalRetraits').text(formatDollar(dashboardData.personalData.totalRetraits));
        $('#nbTransactions').text(dashboardData.personalData.nbTransactions);
    }

    function updateKYCProgress() {
        const steps = Object.values(dashboardData.defaultKYCStatus);
        const completed = steps.filter(s => String(s.status) === '1').length;
        let hasInProgress = steps.some(s => String(s.status) === '2');
        steps.forEach((valObj, idx) => {
            const key = Object.keys(dashboardData.defaultKYCStatus)[idx];
            const val = typeof valObj === 'object' ? String(valObj.status) : String(valObj);
            const $badge = $('#' + key);
            const $icon = $('#' + key.replace('stat', 'icon'));
            if (val === "1") {
                $badge.text('complet').removeClass('bg-danger bg-warning bg-secondary').addClass('bg-success');
                $icon.removeClass('fa-times-circle text-danger fa-clock text-warning').addClass('fa-check-circle text-success');
            } else if (val === "2") {
                $badge.text('En cours').removeClass('bg-success bg-danger bg-secondary').addClass('bg-warning');
                $icon.removeClass('fa-times-circle text-danger fa-check-circle text-success').addClass('fa-clock text-warning');
            } else {
                $badge.text('Incomplet').removeClass('bg-success bg-warning bg-secondary').addClass('bg-danger');
                $icon.removeClass('fa-check-circle text-success fa-clock text-warning').addClass('fa-times-circle text-danger');
            }
        });
        const progress = Math.round((completed / steps.length) * 100);
        const $bar = $('#kycProgressBar');
        const $label = $('#kycStatusLabel');
        $bar.css('width', progress + '%').text(progress + '%').attr('aria-valuenow', progress);
        $bar.removeClass('bg-success bg-warning bg-danger');
        if ($label.length) $label.text(progress === 100 ? 'completed' : 'pending');
        const $statusAlert = $('#alertWarning2');
        const $statusIcon = $('#alertWarning2 i');
        const $statusTitle = $('#alertWarning2 .alert-heading');
        const $statusMsg = $('#alertWarning2 p');
        if (progress === 100) {
            $bar.addClass('bg-success');
            $statusAlert.removeClass('alert-warning').addClass('alert-success');
            $statusIcon.removeClass('fa-exclamation-triangle').addClass('fa-check-circle');
            $statusTitle.text('Vérification terminée');
            $statusMsg.text('Toutes les étapes sont complétées. Merci d\'avoir vérifié votre identité.');
        } else if (hasInProgress) {
            $bar.addClass('bg-warning');
            $statusAlert.removeClass('alert-success').addClass('alert-warning');
            $statusIcon.removeClass('fa-check-circle').addClass('fa-exclamation-triangle');
            $statusTitle.text("La vérification d'identité est en cours");
            $statusMsg.text('Veuillez finaliser les étapes restantes pour terminer la vérification.');
        } else {
            $bar.addClass('bg-danger');
            $statusAlert.removeClass('alert-success').addClass('alert-warning');
            $statusIcon.removeClass('fa-check-circle').addClass('fa-exclamation-triangle');
            $statusTitle.text("La vérification d'identité est requise");
            $statusMsg.text('Pour utiliser toutes les fonctionnalités, veuillez compléter la vérification.');
        }
        renderKYCHistory();
    }

    function renderKYCHistory() {
        const $history = $('#kycHistory');
        if ($history.length === 0) return;
        $history.empty();
        const stepInfo = {
            enregistrementducomptestat: { label: 'Enregistrement du compte', desc: 'Compte créé avec succès' },
            confirmationdeladresseemailstat: { label: "Confirmation de l’adresse e-mail", desc: "Confirmation de l’adresse e-mail réussie" },
            telechargerlesdocumentsdidentitestat: { label: "Docs d’identité à télécharger", desc: "En attente du téléchargement des documents" },
            verificationdeladressestat: { label: "Vérification de l’adresse", desc: "En attente de la vérification de l’adresse" },
            revisionfinalestat: { label: 'Révision finale', desc: 'En attente de la révision finale' }
        };
        Object.keys(dashboardData.defaultKYCStatus).forEach(k => {
            const step = dashboardData.defaultKYCStatus[k];
            const val = typeof step === 'object' ? String(step.status) : String(step);
            const date = (typeof step === 'object' && step.date) ? step.date : '-';
            let badgeClass = 'bg-danger';
            let statusTxt = 'Incomplet';
            if (val === '1') { badgeClass = 'bg-success'; statusTxt = 'complet'; }
            else if (val === '2') { badgeClass = 'bg-warning'; statusTxt = 'En cours'; }
            const info = stepInfo[k] || { label: k, desc: '' };
            $history.append(`
                <div class="timeline-item">
                    <div class="timeline-date">${escapeHtml(date || '-')}</div>
                    <div class="timeline-content">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge ${escapeHtml(badgeClass)} me-2">${escapeHtml(statusTxt)}</span>
                            <h6 class="mb-0">${escapeHtml(info.label)}</h6>
                        </div>
                        <p class="text-muted small">${escapeHtml(info.desc)}</p>
                    </div>
                </div>`);
        });
    }

    function setKYCStatus(key, value, date) {
        if (dashboardData.defaultKYCStatus.hasOwnProperty(key)) {
            const when = date || new Date().toISOString().split('T')[0];
            if (typeof dashboardData.defaultKYCStatus[key] !== 'object') {
                dashboardData.defaultKYCStatus[key] = { status: String(value), date: when };
            } else {
                dashboardData.defaultKYCStatus[key].status = String(value);
                dashboardData.defaultKYCStatus[key].date = when;
            }
            updateKYCProgress();
            saveDashboardData();
        }
    }

    updateKYCProgress();
    window.setKYCStatus = setKYCStatus;

    function populateForm(formId) {
        const formData = dashboardData.formData[formId];
        if (!formData) return;
        $.each(formData, function (key, val) {
            const $el = $('#' + formId + ' #' + key);
            if ($el.is(':checkbox')) {
                $el.prop('checked', val === '1' || val === true);
            } else {
                $el.val(val);
            }
        });
    }

    function saveForm(formId) {
        const formData = {};
        $('#' + formId).find('input, textarea, select').each(function () {
            if (this.id) {
                formData[this.id] = $(this).is(':checkbox') ? (this.checked ? '1' : '0') : $(this).val();
            }
        });
        dashboardData.formData[formId] = formData;
        saveDashboardData();
    }

    [
        'profileEditForm',
        'bankDepositForm',
        'cardDepositForm',
        'cryptoDepositForm',
        'bankWithdrawForm',
        'cryptoWithdrawForm',
        'paypalWithdrawForm',
        'bankAccountForm',
        'changePasswordForm',
        'changeProfilePicForm',
        'addWalletForm'
    ].forEach(populateForm);

    fetchWallets();

    updatePlatformBankDetails();

    $.each(dashboardData.personalData || {}, function (id, value) {
        if (id === "passwordStrengthBar") {
            const $bar = $('#' + id);
            $bar.css("width", value);
            const widthVal = parseInt(value, 10);
            let bgColorClass = "bg-danger";
            if (widthVal >= 70) bgColorClass = "bg-success";
            else if (widthVal >= 40) bgColorClass = "bg-warning";
            $bar.removeClass("bg-success bg-warning bg-danger").addClass(bgColorClass);
        } else if (id === "compteverifie") {
            const showBadge = dashboardData.personalData.compteverifie01 === "1";
            if (showBadge) {
                $('#' + id).text(value).show();
            } else {
                $('#' + id).hide();
            }
        } else {
            const $el = $('#' + id);
            if ($el.is(':checkbox')) {
                $el.prop('checked', value === '1' || value === true);
            } else if ($el.is('input, textarea, select')) {
                $el.val(value);
            } else {
                $el.text(value);
            }
            const $input = $('#' + id + 'Input');
            if ($input.length) {
                if ($input.is(':checkbox')) {
                    $input.prop('checked', value === '1' || value === true);
                } else {
                    $input.val(value);
                }
            }
        }
    });

    $('#bankName').val(dashboardData.personalData.userBankName || '');
    $('#accountHolder').val(dashboardData.personalData.userAccountName || '');
    $('#accountNumber').val(dashboardData.personalData.userAccountNumber || '');
    $('#iban').val(dashboardData.personalData.userIban || '');
    $('#swiftCode').val(dashboardData.personalData.userSwiftCode || '');

    $('#defaultBankName').val(dashboardData.personalData.userBankName || '');
    $('#defaultAccountName').val(dashboardData.personalData.userAccountName || '');
    $('#defaultAccountNumber').val(dashboardData.personalData.userAccountNumber || '');
    $('#defaultIban').val(dashboardData.personalData.userIban || '');
    $('#defaultSwiftCode').val(dashboardData.personalData.userSwiftCode || '');

    const nameValInit = dashboardData.personalData.fullName || '';
    $('#fullNameHeader, #nameincompte').text(nameValInit);
    $('#firstname').text(nameValInit.split(' ')[0] || nameValInit);
    const createdAt = dashboardData.personalData.created_at;
    if (createdAt) {
        const dt = new Date(createdAt);
        const monthYear = dt.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
        $('#memberSince').text('Membre depuis ' + monthYear);
    }
    updateBalances();
    updateCounters();

    const $notifications = $('#notifications');
    if (dashboardData.notifications?.length > 0) {
        dashboardData.notifications.forEach(n => {
            $notifications.append(`
                <div class="alert ${escapeHtml(n.alertClass)}">
                    <strong>${escapeHtml(n.title)}</strong>
                    <p class="mb-0">${escapeHtml(n.message)}</p>
                    <small>${escapeHtml(n.time)}</small>
                </div>`);
        });
    } else {
        $notifications.html('<p>Aucune notification disponible.</p>');
    }

    function generateOperationNumber(type) {
        let prefix = 'T';
        if (type && type.toLowerCase().startsWith('d')) prefix = 'D';
        else if (type && type.toLowerCase().startsWith('r')) prefix = 'R';
        return prefix + Date.now();
    }

    function addTransactionRecord(type, amount, status = 'En cours', statusClass = 'bg-warning', opNum = null) {
        const today = new Date().toISOString().split('T')[0].replace(/-/g, '/');
        dashboardData.transactions = dashboardData.transactions || [];
        const num = opNum || generateOperationNumber(type);
        const adminId = dashboardData.personalData?.linked_to_id || null;
        dashboardData.transactions.unshift({
            admin_id: adminId,
            operationNumber: num,
            type,
            amount,
            date: today,
            status,
            statusClass
        });
        dashboardData.transactions = dashboardData.transactions.slice(0, 10);
    }

    function renderRecentTransactions() {
        const $tbody = $('#transactionsRecents');
        $tbody.empty();
        if (dashboardData.transactions?.length > 0) {
            dashboardData.transactions.slice(0, 5).forEach(t => {
                $tbody.append(`
                    <tr>
                        <td>${escapeHtml(t.operationNumber)}</td>
                        <td>${escapeHtml(t.type)}</td>
                        <td>${formatDollar(t.amount)}</td>
                        <td>${escapeHtml(t.date)}</td>
                        <td><span class="badge ${escapeHtml(t.statusClass)}">${escapeHtml(t.status)}</span></td>
                    </tr>`);
            });
        } else {
            $tbody.html('<tr><td colspan="5" class="text-center">Aucune donnée disponible</td></tr>');
        }
    }

    const notificationIcons = {
        "info": "fas fa-chart-line text-primary",
        "success": "fas fa-money-bill-wave text-success",
        "warning": "fas fa-exclamation-triangle text-warning",
        "error": "fas fa-times-circle text-danger",
        "kyc": "fas fa-user-check text-info",
        "default": "fas fa-bill text-secondary"
    };

    function generateNotificationDropdownItems(notifications) {
        return notifications.map(notification => {
            const iconClass = notificationIcons[notification.type] || notificationIcons.default;
            return `
                <li>
                    <a class="dropdown-item" href="#">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="${escapeHtml(iconClass)}"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold">${escapeHtml(notification.title)}</div>
                                <div class="small text-muted">${escapeHtml(notification.message)}</div>
                                <div class="small text-muted">${escapeHtml(notification.time)}</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>`;
        }).join('');
    }

    const notifications = dashboardData.notifications || [];
    $('#notificationCount').text(notifications.length);
    const $dropdown = $('#notificationsDropdown');
    $dropdown.empty();
    if (notifications.length > 0) {
        $dropdown.append(generateNotificationDropdownItems(notifications));
        $dropdown.append(`
            <li class="text-center">
                <a class="dropdown-item small" href="#">Afficher toutes les notifications</a>
            </li>`);
    } else {
        $dropdown.append(`
            <li class="text-center text-muted py-3">
                <p class="m-0 fw-semibold" style="font-size: 0.95rem;">Aucune donnée disponible actuellement</p>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li class="text-center">
                <a class="dropdown-item small" href="#">Afficher toutes les notifications</a>
            </li>`);
    }

    $('#editProfileBtn').on('click', function () {
        $('#ProfilInfo').hide();
        $('#ProfilEditForm').show();
    });

    $('#saveProfileBtn').on('click', function () {
        saveForm('profileEditForm');
        const nameVal = $('#fullNameInput').val();
        dashboardData.personalData.fullName = nameVal;
        dashboardData.personalData.emailaddress = $('#email').val();
        dashboardData.personalData.phone = $('#phoneInput').val();
        dashboardData.personalData.dob = $('#birthdate').val();
        dashboardData.personalData.nationality = $('#nationalityInput').val();
        dashboardData.personalData.address = $('#addressInput').val();
        $('#fullName').text(nameVal);
        $('#fullNameHeader').text(nameVal);
        $('#nameincompte').text(nameVal);
        $('#firstname').text(nameVal.split(' ')[0] || nameVal);
        $('#emailaddress').text(dashboardData.personalData.emailaddress);
        $('#phone').text(dashboardData.personalData.phone);
        $('#dob').text(dashboardData.personalData.dob);
        $('#nationality').text(dashboardData.personalData.nationality);
        $('#address').text(dashboardData.personalData.address);
        $('#ProfilInfo').show();
        $('#ProfilEditForm').hide();
        saveDashboardData();
    });

    $('#cancelEditBtn').on('click', function () {
        populateForm('profileEditForm');
        $('#ProfilInfo').show();
        $('#ProfilEditForm').hide();
    });

    $('#parametresNotifications input[type="checkbox"]').on('change', function () {
        const key = this.id;
        dashboardData.personalData[key] = this.checked ? '1' : '0';
        saveDashboardData();
    });

    $('#twoFactorAuth').on('change', function () {
        dashboardData.personalData.twoFactorAuth = this.checked ? '1' : '0';
        saveDashboardData();
    });

    async function hashPassword(pwd) {
		return md5(pwd);
	}
		
		 // Minimal MD5 implementation
    function md5(str) {
        function cmn(q, a, b, x, s, t) {
            a = (a + q + x + t) | 0;
            return (((a << s) | (a >>> (32 - s))) + b) | 0;
        }
        function ff(a, b, c, d, x, s, t) {
            return cmn((b & c) | (~b & d), a, b, x, s, t);
        }
        function gg(a, b, c, d, x, s, t) {
            return cmn((b & d) | (c & ~d), a, b, x, s, t);
        }
        function hh(a, b, c, d, x, s, t) {
            return cmn(b ^ c ^ d, a, b, x, s, t);
        }
        function ii(a, b, c, d, x, s, t) {
            return cmn(c ^ (b | ~d), a, b, x, s, t);
        }

        function md5cycle(x, k) {
            let a = x[0], b = x[1], c = x[2], d = x[3];

            a = ff(a, b, c, d, k[0], 7 , -680876936);
            d = ff(d, a, b, c, k[1], 12, -389564586);
            c = ff(c, d, a, b, k[2], 17,  606105819);
            b = ff(b, c, d, a, k[3], 22, -1044525330);
            a = ff(a, b, c, d, k[4], 7 , -176418897);
            d = ff(d, a, b, c, k[5], 12,  1200080426);
            c = ff(c, d, a, b, k[6], 17, -1473231341);
            b = ff(b, c, d, a, k[7], 22, -45705983);
            a = ff(a, b, c, d, k[8], 7 ,  1770035416);
            d = ff(d, a, b, c, k[9], 12, -1958414417);
            c = ff(c, d, a, b, k[10],17, -42063);
            b = ff(b, c, d, a, k[11],22, -1990404162);
            a = ff(a, b, c, d, k[12],7 ,  1804603682);
            d = ff(d, a, b, c, k[13],12, -40341101);
            c = ff(c, d, a, b, k[14],17, -1502002290);
            b = ff(b, c, d, a, k[15],22,  1236535329);

            a = gg(a, b, c, d, k[1], 5 , -165796510);
            d = gg(d, a, b, c, k[6], 9 , -1069501632);
            c = gg(c, d, a, b, k[11],14,  643717713);
            b = gg(b, c, d, a, k[0], 20, -373897302);
            a = gg(a, b, c, d, k[5], 5 , -701558691);
            d = gg(d, a, b, c, k[10],9 ,  38016083);
            c = gg(c, d, a, b, k[15],14, -660478335);
            b = gg(b, c, d, a, k[4], 20, -405537848);
            a = gg(a, b, c, d, k[9], 5 ,  568446438);
            d = gg(d, a, b, c, k[14],9 , -1019803690);
            c = gg(c, d, a, b, k[3], 14, -187363961);
            b = gg(b, c, d, a, k[8], 20,  1163531501);
            a = gg(a, b, c, d, k[13],5 , -1444681467);
            d = gg(d, a, b, c, k[2], 9 , -51403784);
            c = gg(c, d, a, b, k[7], 14,  1735328473);
            b = gg(b, c, d, a, k[12],20, -1926607734);

            a = hh(a, b, c, d, k[5], 4 , -378558);
            d = hh(d, a, b, c, k[8], 11, -2022574463);
            c = hh(c, d, a, b, k[11],16,  1839030562);
            b = hh(b, c, d, a, k[14],23, -35309556);
            a = hh(a, b, c, d, k[1], 4 , -1530992060);
            d = hh(d, a, b, c, k[4], 11,  1272893353);
            c = hh(c, d, a, b, k[7], 16, -155497632);
            b = hh(b, c, d, a, k[10],23, -1094730640);
            a = hh(a, b, c, d, k[13],4 ,  681279174);
            d = hh(d, a, b, c, k[0], 11, -358537222);
            c = hh(c, d, a, b, k[3], 16, -722521979);
            b = hh(b, c, d, a, k[6], 23,  76029189);
            a = hh(a, b, c, d, k[9], 4 , -640364487);
            d = hh(d, a, b, c, k[12],11, -421815835);
            c = hh(c, d, a, b, k[15],16,  530742520);
            b = hh(b, c, d, a, k[2], 23, -995338651);

            a = ii(a, b, c, d, k[0], 6 , -198630844);
            d = ii(d, a, b, c, k[7], 10,  1126891415);
            c = ii(c, d, a, b, k[14],15, -1416354905);
            b = ii(b, c, d, a, k[5], 21, -57434055);
            a = ii(a, b, c, d, k[12],6 ,  1700485571);
            d = ii(d, a, b, c, k[3], 10, -1894986606);
            c = ii(c, d, a, b, k[10],15, -1051523);
            b = ii(b, c, d, a, k[1], 21, -2054922799);
            a = ii(a, b, c, d, k[8], 6 ,  1873313359);
            d = ii(d, a, b, c, k[15],10, -30611744);
            c = ii(c, d, a, b, k[6], 15, -1560198380);
            b = ii(b, c, d, a, k[13],21,  1309151649);
            a = ii(a, b, c, d, k[4], 6 , -145523070);
            d = ii(d, a, b, c, k[11],10, -1120210379);
            c = ii(c, d, a, b, k[2], 15,  718787259);
            b = ii(b, c, d, a, k[9], 21, -343485551);

            x[0] = (a + x[0]) | 0;
            x[1] = (b + x[1]) | 0;
            x[2] = (c + x[2]) | 0;
            x[3] = (d + x[3]) | 0;
        }

        function md51(s) {
            const txt = unescape(encodeURIComponent(s));
            const n = txt.length;
            const state = [1732584193, -271733879, -1732584194, 271733878];
            for (let i = 64; i <= n; i += 64) {
                md5cycle(state, md5blk(txt.substring(i - 64, i)));
            }
            let tail = new Array(16).fill(0);
            let i = 0;
            for (; i < n % 64; i++) {
                tail[i >> 2] |= txt.charCodeAt(n - (n % 64) + i) << ((i % 4) << 3);
            }
            tail[i >> 2] |= 0x80 << ((i % 4) << 3);
            if (i > 55) {
                md5cycle(state, tail);
                tail = new Array(16).fill(0);
            }
            tail[14] = n * 8;
            md5cycle(state, tail);
            return state;
        }

        function md5blk(s) {
            const md5blks = [];
            for (let i = 0; i < 64; i += 4) {
                md5blks[i >> 2] = s.charCodeAt(i) +
                    (s.charCodeAt(i + 1) << 8) +
                    (s.charCodeAt(i + 2) << 16) +
                    (s.charCodeAt(i + 3) << 24);
            }
            return md5blks;
        }

        function rhex(n) {
            let s = '';
            for (let j = 0; j < 4; j++) {
                s += ((n >> (j * 8 + 4)) & 0x0f).toString(16) +
                    ((n >> (j * 8)) & 0x0f).toString(16);
            }
            return s;
        }

        function hex(x) {
            return x.map(rhex).join('');
        }

        return hex(md51(str));
    }

    function computePasswordStrength(pwd) {
        let score = 0;
        if (pwd.length >= 6) score += 5;
        if (pwd.length >= 8) score += 30;
        if (/[A-Z]/.test(pwd)) score += 20;
        if (/[0-9]/.test(pwd)) score += 20;
        if (/[^A-Za-z0-9]/.test(pwd)) score += 30;
        return Math.min(score, 100);
    }

    function strengthLabel(score) {
        if (score >= 90) return 'Fort';
        if (score >= 50) return 'Moyen';
        return 'Faible';
    }

    function barClass(score) {
        if (score >= 90) return 'bg-success';
        if (score >= 50) return 'bg-warning';
        return 'bg-danger';
    }

    $('#savePasswordBtn').on('click', async function () {
        const current = $('#currentPassword').val();
        const newPw = $('#newPassword').val();
        const confirm = $('#confirmPassword').val();
        const currentHash = await hashPassword(current);
        if (currentHash !== dashboardData.personalData.passwordHash) {
            alert('Mot de passe actuel incorrect');
            return;
        }
        if (newPw !== confirm) {
            alert('Les nouveaux mots de passe ne correspondent pas.');
            return;
        }
        dashboardData.personalData.passwordHash = await hashPassword(newPw);
        const score = computePasswordStrength(newPw);
        const label = strengthLabel(score);
        const cls = barClass(score);
        $('#passwordStrength')
            .text(label)
            .removeClass('bg-success bg-warning bg-danger')
            .addClass(cls);
        $('#passwordStrengthBar')
            .css('width', score + "%")
            .attr('aria-valuenow', score)
            .removeClass('bg-success bg-warning bg-danger')
            .addClass(cls);
        dashboardData.personalData.passwordStrength = label;
        dashboardData.personalData.passwordStrengthBar = score + '%';
        $('#changePasswordModal').modal('hide');
        $('#changePasswordForm')[0].reset();
        saveDashboardData();
    });

    $('#bankDepositForm, #cardDepositForm, #cryptoDepositForm, #bankWithdrawForm, #cryptoWithdrawForm, #paypalWithdrawForm, #bankAccountForm, #changePasswordForm, #changeProfilePicForm').on('submit', function (e) {
        e.preventDefault();
        saveForm(this.id);
        const today = new Date().toISOString().split('T')[0].replace(/-/g, '/');
        if (['bankWithdrawForm', 'cryptoWithdrawForm', 'paypalWithdrawForm'].includes(this.id)) {
            const amountField = {
                bankWithdrawForm: '#withdrawAmount',
                cryptoWithdrawForm: '#cryptoWithdrawAmount',
                paypalWithdrawForm: '#paypalWithdrawAmount'
            }[this.id];
            const amt = parseFloat($(amountField).val());
            if (!isNaN(amt) && amt > 0) {
                const cur = parseDollar(dashboardData.personalData.balance);
                dashboardData.personalData.balance = cur - amt;
                dashboardData.personalData.totalRetraits =
                    parseDollar(dashboardData.personalData.totalRetraits) + amt;
                dashboardData.personalData.nbTransactions =
                    (parseInt(dashboardData.personalData.nbTransactions) || 0) + 1;
                const method = this.id === 'bankWithdrawForm' ? 'Banque' :
                    this.id === 'paypalWithdrawForm' ? 'Paypal' :
                    (currencyNames[$('#cryptoCurrencyWithdraw').val()] || 'Crypto');
                dashboardData.retraits = dashboardData.retraits || [];
                const opNumR = generateOperationNumber('R');
                const adminId = dashboardData.personalData?.linked_to_id || null;
                dashboardData.retraits.unshift({
                    admin_id: adminId,
                    operationNumber: opNumR,
                    date: today,
                    amount: amt,
                    method,
                    status: 'En cours',
                    statusClass: 'bg-warning'
                });
                dashboardData.retraits = dashboardData.retraits.slice(0, 10);
                addTransactionRecord('Retrait', amt, 'En cours', 'bg-warning', opNumR);
                updateBalances();
                updateCounters();
                renderWithdrawHistory();
                renderRecentTransactions();
                showBootstrapAlert('withdrawAlert', 'Votre demande sera traitée dans les plus brefs délais.', 'success');
                saveDashboardData();
            }
            if (this.id === 'bankWithdrawForm' && $('#saveBankInfo').is(':checked')) {
                dashboardData.personalData.userBankName = $('#bankName').val();
                dashboardData.personalData.userAccountName = $('#accountHolder').val();
                dashboardData.personalData.userAccountNumber = $('#accountNumber').val();
                dashboardData.personalData.userIban = $('#iban').val();
                dashboardData.personalData.userSwiftCode = $('#swiftCode').val()
                dashboardData.bankWithdrawInfo = {
                    widhrawBankName: $('#bankName').val(),
                    widhrawAccountName: $('#accountHolder').val(),
                    widhrawAccountNumber: $('#accountNumber').val(),
                    widhrawIban: $('#iban').val(),
                    widhrawSwiftCode: $('#swiftCode').val()
                };


                $('#defaultBankName').val($('#bankName').val());
                $('#defaultAccountName').val($('#accountHolder').val());
                $('#defaultAccountNumber').val($('#accountNumber').val());
                $('#defaultIban').val($('#iban').val());
                $('#defaultSwiftCode').val($('#swiftCode').val());
                saveDashboardData();
                updatePlatformBankDetails();
            }
        } else if (['bankDepositForm', 'cardDepositForm', 'cryptoDepositForm'].includes(this.id)) {
            const amountField = {
                bankDepositForm: '#bankDepositAmount',
                cardDepositForm: '#cardDepositAmount',
                cryptoDepositForm: '#cryptoAmount'
            }[this.id];
            const amt = parseFloat($(amountField).val());
            if (!isNaN(amt) && amt > 0) {
                const cur = parseDollar(dashboardData.personalData.balance);
                dashboardData.personalData.balance = cur + amt;
                dashboardData.personalData.totalDepots =
                    parseDollar(dashboardData.personalData.totalDepots) + amt;
                dashboardData.personalData.nbTransactions =
                    (parseInt(dashboardData.personalData.nbTransactions) || 0) + 1;
                const method = this.id === 'bankDepositForm' ? 'Banque' :
                    this.id === 'cardDepositForm' ? 'Carte' :
                    (currencyNames[$('#cryptoCurrency').val()] || 'Crypto');
                dashboardData.deposits = dashboardData.deposits || [];
                const opNumD = generateOperationNumber('D');
                const adminId2 = dashboardData.personalData?.linked_to_id || null;
                dashboardData.deposits.unshift({
                    admin_id: adminId2,
                    operationNumber: opNumD,
                    date: today,
                    amount: amt,
                    method,
                    status: 'En cours',
                    statusClass: 'bg-warning'
                });
                dashboardData.deposits = dashboardData.deposits.slice(0, 10);
                renderDepositHistory();
                updateBalances();
                updateCounters();
                addTransactionRecord('Dépôt', amt, 'En cours', 'bg-warning', opNumD);
                renderRecentTransactions();
                showBootstrapAlert('depositAlert', 'Votre demande sera traitée dans les plus brefs délais.', 'success');
                saveDashboardData();
            }
        }
        if (this.id === 'bankAccountForm') {
            dashboardData.personalData.userBankName = $('#defaultBankName').val();
            dashboardData.personalData.userAccountName = $('#defaultAccountName').val();
            dashboardData.personalData.userAccountNumber = $('#defaultAccountNumber').val();
            dashboardData.personalData.userIban = $('#defaultIban').val();
            dashboardData.personalData.userSwiftCode = $('#defaultSwiftCode').val();

            dashboardData.bankWithdrawInfo = {
                widhrawBankName: $('#defaultBankName').val(),
                widhrawAccountName: $('#defaultAccountName').val(),
                widhrawAccountNumber: $('#defaultAccountNumber').val(),
                widhrawIban: $('#defaultIban').val(),
                widhrawSwiftCode: $('#defaultSwiftCode').val()
            };
            $('#bankName').val($('#defaultBankName').val());
            $('#accountHolder').val($('#defaultAccountName').val());
            $('#accountNumber').val($('#defaultAccountNumber').val());
            $('#iban').val($('#defaultIban').val());
            $('#swiftCode').val($('#defaultSwiftCode').val());
            saveDashboardData();
            updatePlatformBankDetails();
            $('#bankAccountAlert').html(`
                <div id="withdrawAlert">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>Vos informations bancaires ont été enregistrées avec succès.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>`);

        }
    });

    $('.upload-area').each(function () {
        const $area = $(this);
        const $input = $area.find('input[type="file"]');
        const displayFile = (fileName) => {
            $area.html(`
                <i class="fas fa-file-alt fa-3x mb-3"></i>
                <h5>${escapeHtml(fileName)}</h5>
                <p class="text-muted">Cliquez pour modifier le profil</p>`);
        };
        $area.on('click', () => $input.click());
        $input.on('change', function () {
            if (this.files.length > 0) {
                displayFile(this.files[0].name);
            }
        });
        $area.on('dragover', (e) => {
            e.preventDefault();
            $area.addClass('border-primary').css('backgroundColor', 'rgba(52, 152, 219, 0.1)');
        });
        $area.on('dragleave drop', function (e) {
            e.preventDefault();
            $area.removeClass('border-primary').css('backgroundColor', '');
            if (e.type === 'drop' && e.originalEvent.dataTransfer.files.length > 0) {
                $input[0].files = e.originalEvent.dataTransfer.files;
                displayFile(e.originalEvent.dataTransfer.files[0].name);
            }
        });
    });

    $('.nav-link').each(function () {
        const tabTrigger = new bootstrap.Tab(this);
        $(this).on('click', function (e) {
            e.preventDefault();
            tabTrigger.show();
        });
    });

    const networksByCurrency = {
        btc: ['Bitcoin'],
        bch: ['BCH'],
        eth: ['ERC20', 'BEP20', 'TRC20'],
        ltc: ['Litecoin'],
        usdt: ['ERC20', 'BEP20', 'TRC20'],
        usdc: ['ERC20', 'BEP20', 'TRC20']
    };

    function populateNetworks() {
        const currency = $('#walletCurrency').val();
        const $net = $('#walletNetwork');
        $net.empty().append('<option value="">-- Choisissez le réseau --</option>');
        (networksByCurrency[currency] || []).forEach(n => {
            $net.append(`<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`);
        });
    }

    function populateEditNetworks(currency) {
        const $net = $('#editWalletNetwork');
        $net.empty().append('<option value="">-- Choisissez le réseau --</option>');
        (networksByCurrency[currency] || []).forEach(n => {
            $net.append(`<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`);
        });
    }

    $('#walletCurrency').on('change', populateNetworks);

    function populateCryptoNetwork() {
        const currency = $('#cryptoCurrencyWithdraw').val() || $('#cryptoCurrency').val();
        const $net = $('#cryptoNetwork');
        if ($net.length === 0) return;
        $net.empty().append('<option value="">-- Choisissez le réseau --</option>');
        (networksByCurrency[currency] || []).forEach(n => {
            $net.append(`<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`);
        });
    }

    $('#cryptoCurrencyWithdraw').on('change', populateCryptoNetwork);
    $('#cryptoCurrency').on('change', populateCryptoNetwork);
    populateCryptoNetwork();

    function updateCryptoDepositAddress() {
        const currency = $('#cryptoCurrency').val();
        const key = currency + 'Address';
        $('#cryptoDepositAddress').val(dashboardData.personalData[key] || '');
    }

    $('#cryptoCurrency').on('change', updateCryptoDepositAddress);
    $('#cryptoDepositAddress').on('input', function () {
        const currency = $('#cryptoCurrency').val();
        const key = currency + 'Address';
        dashboardData.personalData[key] = $(this).val();
        saveDashboardData();
    });

    updateCryptoDepositAddress();

    $(document).on('click', '.wallet-delete', async function () {
        const id = $(this).data('id');
        if (confirm('Êtes-vous sûr de vouloir supprimer cette adresse ?')) {
            await fetch('get_wallets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id, user_id: userId })
            });
            await fetchWallets();
        }
    });

    let currentEditWalletId = null;
    $(document).on('click', '.wallet-edit', function () {
        const id = Number($(this).data('id'));
        const wallet = (dashboardData.personalData.wallets || []).find(w => Number(w.id) === id);
        console.log(wallet)
		if (!wallet) return;
        currentEditWalletId = id;
		console.log(currentEditWalletId)
        populateEditNetworks(wallet.currency);
        $('#editWalletNetwork').val(wallet.network || '');
        $('#editWalletAddress').val(wallet.address || '');
        $('#editWalletLabel').val(wallet.label || '');
        $('#editWalletModal').modal('show');
    });

    $('#editWalletModal').on('hidden.bs.modal', function () {
        currentEditWalletId = null;
    });

    $('#saveWalletEditBtn').on('click', async function () {
        const address = $('#editWalletAddress').val().trim();
        const network = $('#editWalletNetwork').val();
        const label = $('#editWalletLabel').val().trim();
        if (!address || !network) {
            alert('Veuillez remplir tous les champs requis.');
            return;
        }
        try {
            const res = await fetch('get_wallets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'edit', id: currentEditWalletId, address, label, network, user_id: userId })
            });
            const result = await res.json();
            if (!res.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Erreur lors de la mise \u00e0 jour');
            }
            const wallets = dashboardData.personalData.wallets || [];
            const wallet = wallets.find(w => w.id === currentEditWalletId);
            if (wallet) {
                wallet.network = network;
                wallet.address = address;
                wallet.label = label;
            }
            const $row = $('#walletTableBody').find(`tr[data-id="${currentEditWalletId}"]`);
            $row.children().eq(1).text(network);
            $row.find('.wallet-address').text(address);
            $('#editWalletModal').modal('hide');
        } catch (err) {
            alert(err.message || 'Erreur lors de la mise \u00e0 jour');
        }
    });

    $('#addWalletBtn').on('click', async function () {
        const currency = $('#walletCurrency').val();
        const network = $('#walletNetwork').val();
        const address = $('#walletAddressNew').val().trim();
        const label = $('#walletLabel').val().trim();
        if (!currency || !network || !address) {
            alert('Veuillez remplir tous les champs requis.');
            return;
        }
        const wallet = {
            id: Date.now(),
            currency,
            network,
            address,
            label
        };
        dashboardData.personalData.wallets = dashboardData.personalData.wallets || [];
        dashboardData.personalData.wallets.push(wallet);
        saveForm('addWalletForm');
        await saveDashboardData();
        await fetchWallets();
        $('#addWalletModal').modal('hide');
        $('#walletCurrency').val('');
        populateNetworks();
        $('#walletAddressNew').val('');
        $('#walletLabel').val('');
    });

    function renderDepositHistory() {
        const $tbodyDeposits = $('#historiqueDepots');
        $tbodyDeposits.empty();
        if (dashboardData.deposits?.length > 0) {
            dashboardData.deposits.sort((a, b) => new Date(b.date) - new Date(a.date));
            dashboardData.deposits.slice(0, 10).forEach(d => {
                $tbodyDeposits.append(`
                    <tr>
                        <td>${escapeHtml(d.date)}</td>
                        <td>${formatDollar(d.amount)}</td>
                        <td>${escapeHtml(d.method)}</td>
                        <td><span class="badge ${escapeHtml(d.statusClass)}">${escapeHtml(d.status)}</span></td>
                    </tr>`);
            });
        } else {
            $tbodyDeposits.html('<tr><td colspan="4" class="text-center">Aucune donnée disponible</td></tr>');
        }
    }

    function renderWithdrawHistory() {
        const $tbodyRetraits = $('#historiqueRetraits');
        $tbodyRetraits.empty();
        if (dashboardData.retraits?.length > 0) {
            dashboardData.retraits.sort((a, b) => new Date(b.date) - new Date(a.date));
            dashboardData.retraits.slice(0, 10).forEach(r => {
                $tbodyRetraits.append(`
                    <tr>
                        <td>${escapeHtml(r.date)}</td>
                        <td>${formatDollar(r.amount)}</td>
                        <td>${escapeHtml(r.method)}</td>
                        <td><span class="badge ${escapeHtml(r.statusClass)}">${escapeHtml(r.status)}</span></td>
                    </tr>`);
            });
        } else {
            $tbodyRetraits.html('<tr><td colspan="4" class="text-center">Aucune donnée disponible</td></tr>');
        }
    }

    renderDepositHistory();
    renderWithdrawHistory();
    renderRecentTransactions();

    const apiPairs = {
        BTCUSD: 'BTCUSDT',
        ETHUSD: 'ETHUSDT',
        ADAUSD: 'ADAUSDT',
        DOTUSD: 'DOTUSDT',
        LINKUSD: 'LINKUSDT',
        LTCUSD: 'LTCUSDT',
        XRPUSD: 'XRPUSDT'
    };

    let currentPrice = 0;
    let priceChange = 0;

    function renderTradingHistory() {
        const $tbodyTrading = $('#tradingHistory');
        $tbodyTrading.empty();
        if (dashboardData.tradingHistory?.length > 0) {
            dashboardData.tradingHistory.slice(0, 5).forEach(trade => {
                $tbodyTrading.append(`
                    <tr>
                        <td>${escapeHtml(trade.operationNumber)}</td>
                        <td>${escapeHtml(trade.temps)}</td>
                        <td>${escapeHtml(trade.paireDevises)}</td>
                        <td><span class="badge ${escapeHtml(trade.statutTypeClass)}">${escapeHtml(trade.type)}</span></td>
                        <td>${formatDollar(trade.montant)}</td>
                        <td>${formatDollar(trade.prix)}</td>
                        <td><span class="badge ${escapeHtml(trade.statutClass)}">${escapeHtml(trade.statut)}</span></td>
                        <td class="${escapeHtml(trade.profitClass || '')}">${trade.profitPerte==null?'-':formatDollar(trade.profitPerte)}</td>
                    </tr>`);
            });
        } else {
            $tbodyTrading.html('<tr><td colspan="8" class="text-center">Aucune donnée disponible</td></tr>');
        }
    }

    function updatePriceUI() {
        $('#currentPrice').text('$' + currentPrice.toLocaleString());
        const changeText = priceChange.toFixed(2) + '%';
        $('#priceChange')
            .text(changeText)
            .removeClass('text-success text-danger')
            .addClass(priceChange >= 0 ? 'text-success' : 'text-danger');
    }

    function fetchPrice(pair) {
        const symbol = apiPairs[pair] || 'BTCUSDT';
        fetch(`https://api.binance.com/api/v3/ticker/24hr?symbol=${symbol}`)
            .then(r => r.json())
            .then(info => {
                currentPrice = parseFloat(info.lastPrice);
                priceChange = parseFloat(info.priceChangePercent);
                updatePriceUI();
                checkStopLosses();
                checkStopLimitOrders();
                checkTakeProfits();
                checkStopOrders();
            })
            .catch(() => {
                $('#currentPrice').text('N/A');
                $('#priceChange').text('-');
            });
    }

    function addTrade(order) {
        dashboardData.tradingHistory = dashboardData.tradingHistory || [];
        if (!order.operationNumber) {
            order.operationNumber = generateOperationNumber('T');
        }
        order.admin_id = dashboardData.personalData?.linked_to_id || null;
        dashboardData.tradingHistory.unshift(order);
        dashboardData.tradingHistory = dashboardData.tradingHistory.slice(0, 5);
        addTransactionRecord('Trading', order.montant, order.statut, order.statutClass, order.operationNumber);
        saveDashboardData();
        renderTradingHistory();
        renderRecentTransactions();
    }

    function finalizeOrder(order, exitPrice) {
        const priceValue = parseFloat(order.prix);
        const qty = parseFloat(order.montant);
        let profit = 0;
        if (order.type === 'Acheter') {
            profit = (exitPrice - priceValue) * qty;
        } else {
            profit = (priceValue - exitPrice) * qty;
        }
        order.profitPerte = profit;
        order.profitClass = profit >= 0 ? 'text-success' : 'text-danger';
        order.statut = 'complet';
        order.statutClass = 'bg-success';
        const tx = (dashboardData.transactions || []).find(t => t.operationNumber === order.operationNumber);
        if (tx) {
            tx.status = 'complet';
            tx.statusClass = 'bg-success';
        }
        if (order.ocoId) {
            dashboardData.tradingHistory.forEach(o => {
                if (o !== order && o.ocoId === order.ocoId && o.statut === 'En cours') {
                    o.statut = 'annulé';
                    o.statutClass = 'bg-secondary';
                }
            });
        }
        const invested = order.invested || priceValue * qty;
        let balance = parseDollar(dashboardData.personalData.balance);
        balance += invested + profit;
        dashboardData.personalData.balance = balance;
        saveDashboardData();
        updateBalances();
        renderTradingHistory();
        renderRecentTransactions();
    }

    function completeOrder(order) {
        setTimeout(() => {
            finalizeOrder(order, currentPrice);
        }, 1500);
    }

    function checkStopLosses() {
        if (!dashboardData.tradingHistory) return;
        dashboardData.tradingHistory.forEach(order => {
            if (order.statut !== 'En cours' || !order.stopLoss) {
                return;
            }
            const sl = order.stopLoss;
            const entryPrice = parseFloat(order.prix);
            if (sl.type === 'price') {
                if ((order.type === 'Acheter' && currentPrice <= sl.price) ||
                    (order.type === 'Vendre' && currentPrice >= sl.price)) {
                    finalizeOrder(order, currentPrice);
                }
            } else if (sl.type === 'percentage') {
                const diff = ((currentPrice - entryPrice) / entryPrice) * 100;
                if ((order.type === 'Acheter' && diff <= -sl.percentage) ||
                    (order.type === 'Vendre' && diff >= sl.percentage)) {
                    finalizeOrder(order, currentPrice);
                }
            } else if (sl.type === 'time') {
                if (Date.now() >= new Date(sl.time).getTime()) {
                    finalizeOrder(order, currentPrice);
                }
            } else if (sl.type === 'trailing') {
                if (order.type === 'Acheter') {
                    sl.highest = Math.max(sl.highest || entryPrice, currentPrice);
                    const trigger = sl.highest * (1 - sl.percentage / 100);
                    if (currentPrice <= trigger) {
                        finalizeOrder(order, currentPrice);
                    }
                } else {
                    sl.lowest = Math.min(sl.lowest || entryPrice, currentPrice);
                    const trigger = sl.lowest * (1 + sl.percentage / 100);
                    if (currentPrice >= trigger) {
                        finalizeOrder(order, currentPrice);
                    }
                }
                saveDashboardData();
            }
        });
    }

    function checkStopLimitOrders() {
        if (!dashboardData.tradingHistory) return;
        dashboardData.tradingHistory.forEach(order => {
            if (order.statut !== 'En cours' || !order.stopLimit) return;
            const sl = order.stopLimit;
            const activated = sl.activated || false;
            if (!activated) {
                if ((order.type === 'Acheter' && currentPrice >= sl.stopPrice) ||
                    (order.type === 'Vendre' && currentPrice <= sl.stopPrice)) {
                    sl.activated = true;
                }
            }
            if (sl.activated) {
                if ((order.type === 'Acheter' && currentPrice <= sl.limitPrice) ||
                    (order.type === 'Vendre' && currentPrice >= sl.limitPrice)) {
                    finalizeOrder(order, sl.limitPrice);
                }
            }
        });
    }

    function checkTakeProfits() {
        if (!dashboardData.tradingHistory) return;
        dashboardData.tradingHistory.forEach(order => {
            if (order.statut !== 'En cours' || order.takeProfit == null) return;
            if ((order.type === 'Acheter' && currentPrice >= order.takeProfit) ||
                (order.type === 'Vendre' && currentPrice <= order.takeProfit)) {
                finalizeOrder(order, order.takeProfit);
            }
        });
    }

    function checkStopOrders() {
        if (!dashboardData.tradingHistory) return;
        dashboardData.tradingHistory.forEach(order => {
            if (order.statut !== 'En cours' || order.stopPrice == null) return;
            if ((order.type === 'Acheter' && currentPrice >= order.stopPrice) ||
                (order.type === 'Vendre' && currentPrice <= order.stopPrice)) {
                finalizeOrder(order, currentPrice);
            }
        });
    }

    $('#currencyPair').on('change', function () {
        const pair = $(this).val();
        fetchPrice(pair);
    });

    $('#orderType').on('change', function () {
        const val = $(this).val();
        $('#limitPriceDiv').toggle(val === 'limit' || val === 'stoplimit');
        $('#stopPriceDiv').toggle(val === 'stop' || val === 'stoplimit');
    });

    $('#enableStopLoss').on('change', function () {
        $('#stopLossSettings').toggle(this.checked);
    });

    $('#enableOCO').on('change', function () {
        $('#takeProfitDiv').toggle(this.checked);
    });

    $('#stopLossType').on('change', function () {
        const val = $(this).val();
        $('#stopLossPriceDiv').toggle(val === 'price');
        $('#stopLossPercentageDiv').toggle(val === 'percentage');
        $('#stopLossTimeDiv').toggle(val === 'time');
        $('#trailingPercentageDiv').toggle(val === 'trailing');
    });

    $('#enableStopLoss').trigger('change');
    $('#stopLossType').trigger('change');
    $('#enableOCO').trigger('change');

    $('#buyBtn, #sellBtn').on('click', function () {
        const isBuy = this.id === 'buyBtn';
        const pair = $('#currencyPair').val();
        const amount = parseFloat($('#tradeAmount').val());
        if (!amount) {
            alert('Veuillez entrer un montant valide');
            return;
        }
        const orderType = $('#orderType').val();
        let price = currentPrice;
        let stopPrice = null;
        if (orderType === 'limit' || orderType === 'stoplimit') {
            price = parseFloat($('#limitPrice').val());
            if (!price) {
                alert('Veuillez entrer le prix souhaité');
                return;
            }
        }
        if (orderType === 'stop' || orderType === 'stoplimit') {
            stopPrice = parseFloat($('#stopPrice').val());
            if (!stopPrice) {
                alert('Veuillez entrer le prix stop');
                return;
            }
        }
        const cost = amount * price;
        if (cost > parseDollar(dashboardData.personalData.balance)) {
            alert('Solde insuffisant');
            return;
        }
        let newBalance = parseDollar(dashboardData.personalData.balance) - cost;
        dashboardData.personalData.balance = newBalance;
        saveDashboardData();
        updateBalances();

        let stopLoss = null;
        if ($('#enableStopLoss').is(':checked')) {
            const slType = $('#stopLossType').val();
            if (slType === 'price') {
                const slPrice = parseFloat($('#stopLossPrice').val());
                if (slPrice) stopLoss = { type: 'price', price: slPrice };
            } else if (slType === 'percentage') {
                const slPerc = parseFloat($('#stopLossPercentage').val());
                if (slPerc) stopLoss = { type: 'percentage', percentage: slPerc };
            } else if (slType === 'time') {
                const slTime = $('#stopLossTime').val();
                if (slTime) stopLoss = { type: 'time', time: slTime };
            } else if (slType === 'trailing') {
                const trail = parseFloat($('#trailingPercentage').val());
                if (trail) stopLoss = { type: 'trailing', percentage: trail, highest: price, lowest: price };
            }
        }

        const ocoEnabled = $('#enableOCO').is(':checked');
        const takeProfit = ocoEnabled ? parseFloat($('#takeProfitPrice').val()) : null;
        if (ocoEnabled && (!stopLoss || !takeProfit)) {
            alert('Veuillez définir un stop loss et un take profit pour OCO');
            return;
        }

        const ocoId = ocoEnabled ? Date.now() : null;
        const order = {
            temps: new Date().toLocaleString(),
            paireDevises: pair.replace('USD', '/USD'),
            type: isBuy ? 'Acheter' : 'Vendre',
            statutTypeClass: isBuy ? 'bg-success' : 'bg-danger',
            montant: amount,
            prix: price,
            statut: 'En cours',
            statutClass: 'bg-warning',
            profitPerte: null,
            profitClass: '',
            stopLoss: stopLoss,
            stopLimit: orderType === 'stoplimit' ? { stopPrice: stopPrice, limitPrice: price } : null,
            stopPrice: orderType === 'stop' ? stopPrice : null,
            takeProfit: ocoEnabled ? takeProfit : null,
            ocoId: ocoId,
            invested: cost
        };

        if (ocoEnabled) {
            const tpOrder = Object.assign({}, order, { stopLoss: null, takeProfit: takeProfit, prix: takeProfit });
            const slOrder = Object.assign({}, order, { takeProfit: null });
            addTrade(tpOrder);
            addTrade(slOrder);
        } else {
            addTrade(order);
        }

        if (orderType === 'market') {
            if (ocoEnabled) {
                // for OCO market orders, mark both pending until conditions met
            } else {
                completeOrder(order);
            }
        }
    });

    fetchPrice($('#currencyPair').val());
    setInterval(() => fetchPrice($('#currencyPair').val()), 1000);
    renderTradingHistory();

    const $loginHistoryBody = $('#loginHistoryBody');
    if (dashboardData.loginHistory?.length > 0) {
        dashboardData.loginHistory.slice(0, 5).forEach(h => {
            $loginHistoryBody.append(`
                <tr>
                    <td>${escapeHtml(h.date)}</td>
                    <td>${escapeHtml(h.ip)}</td>
                    <td>${escapeHtml(h.device)}</td>
                </tr>`);
        });
    } else {
        $loginHistoryBody.html('<tr><td colspan="3" class="text-center">Aucune donnée disponible</td></tr>');
    }
};