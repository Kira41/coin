$(document).ready(async function () {
    // ======================== إعداد البيانات ========================

    async function loadDashboardData() {
        try {
            const result = await $.getJSON('getter.php');
            if (result && !result.error) {
                return result;
            }
        } catch (e) {
            // ignore
        }
        return {};
    }

    const dashboardData = await loadDashboardData();
    let data = dashboardData || {};
    const loadSucceeded = !!(Object.keys(data).length);
    data.personalData = data.personalData || {};
    data.personalData.wallets = dashboardData.wallets || [];
    if (data.personalData.balance === undefined) {
        const fallback = data.personalData.soldeTotal || data.personalData.soldeintrade || data.personalData.soldedisponible1;
        data.personalData.balance = fallback || '0 $';
    }
    data.formData = data.formData || {};
    data.defaultKYCStatus = data.defaultKYCStatus || {
        enregistrementducomptestat: { status: "0", date: "" },
        confirmationdeladresseemailstat: { status: "0", date: "" },
        telechargerlesdocumentsdidentitestat: { status: "0", date: "" },
        verificationdeladressestat: { status: "0", date: "" },
        revisionfinalestat: { status: "0", date: "" }
    };

    const defaultKYCStatus = data.defaultKYCStatus;

    let kycStatus = data.kycStatus;
    if (!kycStatus) {
        kycStatus = JSON.parse(JSON.stringify(defaultKYCStatus));
    } else {
        Object.keys(defaultKYCStatus).forEach(k => {
            const v = kycStatus[k];
            if (!v) {
                kycStatus[k] = { status: "0", date: "" };
            } else if (typeof v === 'object') {
                kycStatus[k] = { status: String(v.status), date: v.date };
            } else {
                kycStatus[k] = { status: String(v), date: "" };
            }
        });
    }
    if (data.personalData && data.personalData.password) {
        data.personalData.passwordHash = await hashPassword(data.personalData.password);
        delete data.personalData.password;
        if (loadSucceeded) {
            saveData();
        }
    } else if (loadSucceeded) {
        saveData();
    }

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

    function saveData() {
        const pd = { ...data.personalData };
        const wallets = pd.wallets || [];
        delete pd.wallets;
        $.ajax({
            url: 'setter.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                personalData: pd,
                wallets: wallets,
                transactions: data.transactions,
                notifications: data.notifications,
                deposits: data.deposits,
                retraits: data.retraits,
                tradingHistory: data.tradingHistory,
                loginHistory: data.loginHistory,
                formData: data.formData,
                kycStatus: kycStatus
            })
        });
    }

    function updateBalances() {
        const bal = formatDollar(parseDollar(data.personalData.balance));
        $('#soldeTotal').text(bal);
        $('#soldeintrade').text(bal);
        $('#soldedisponible1').text(bal);
        $('#soldedisponible2').text(bal);
        $('#soldedisponible3').text(bal);
        $('#accountBalance').text(bal);
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
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas ${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        $('#'+containerId).html(alertHtml);
    }

    function updateKYCProgress() {
        let completedSteps = 0;
        let hasInProgress = false;
        const keys = Object.keys(defaultKYCStatus);
        keys.forEach(k => {
            const valObj = kycStatus[k] || defaultKYCStatus[k];
            const val = typeof valObj === 'object' ? String(valObj.status) : String(valObj);
            const $badge = $(`#${k}`);
            const $icon = $(`#${k.replace('stat','icon')}`);
            if (val === "1") {
                $badge.text('complet').removeClass('bg-danger bg-warning bg-secondary').addClass('bg-success');
                $icon.removeClass('fa-times-circle text-danger fa-clock text-warning').addClass('fa-check-circle text-success');
                completedSteps++;
            } else if (val === "2") {
                $badge.text('En cours').removeClass('bg-success bg-danger bg-secondary').addClass('bg-warning');
                $icon.removeClass('fa-times-circle text-danger fa-check-circle text-success').addClass('fa-clock text-warning');
                hasInProgress = true;
            } else {
                $badge.text('Incomplet').removeClass('bg-success bg-warning bg-secondary').addClass('bg-danger');
                $icon.removeClass('fa-check-circle text-success fa-clock text-warning').addClass('fa-times-circle text-danger');
            }
        });
        const progress = (completedSteps / keys.length) * 100;
        const $bar = $('#kycProgressBar');
        $bar.css('width', progress + '%').text(progress + '%').attr('aria-valuenow', progress);
        $bar.removeClass('bg-success bg-warning bg-danger');
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
        Object.keys(defaultKYCStatus).forEach(k => {
            const step = kycStatus[k] || defaultKYCStatus[k];
            const val = typeof step === 'object' ? String(step.status) : String(step);
            const date = (typeof step === 'object' && step.date) ? step.date : '-';
            let badgeClass = 'bg-danger';
            let statusTxt = 'Incomplet';
            if (val === '1') { badgeClass = 'bg-success'; statusTxt = 'complet'; }
            else if (val === '2') { badgeClass = 'bg-warning'; statusTxt = 'En cours'; }
            const info = stepInfo[k] || { label: k, desc: '' };
            $history.append(`
                <div class="timeline-item">
                    <div class="timeline-date">${date || '-'}</div>
                    <div class="timeline-content">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge ${badgeClass} me-2">${statusTxt}</span>
                            <h6 class="mb-0">${info.label}</h6>
                        </div>
                        <p class="text-muted small">${info.desc}</p>
                    </div>
                </div>`);
        });
    }

    function setKYCStatus(key, value, date) {
        if (defaultKYCStatus.hasOwnProperty(key)) {
            const when = date || new Date().toISOString().split('T')[0];
            if (typeof kycStatus[key] !== 'object') {
                kycStatus[key] = { status: String(value), date: when };
            } else {
                kycStatus[key].status = String(value);
                kycStatus[key].date = when;
            }
            updateKYCProgress();
        }
    }

    updateKYCProgress();
    window.setKYCStatus = setKYCStatus;

    function populateForm(formId) {
        const formData = data.formData[formId];
        if (!formData) return;
        $.each(formData, function(key, val) {
            const $el = $(`#${formId} #${key}`);
            if ($el.is(':checkbox')) {
                $el.prop('checked', val === '1' || val === true);
            } else {
                $el.val(val);
            }
        });
    }

    function saveForm(formId) {
        const formData = {};
        $(`#${formId}`).find('input, textarea, select').each(function () {
            if (this.id) {
                formData[this.id] = $(this).is(':checkbox') ? (this.checked ? '1' : '0') : $(this).val();
            }
        });
        data.formData[formId] = formData;
        saveData();
    }

    const bankFieldMap = {
        defaultBankName: 'bankName',
        defaultAccountName: 'accountHolder',
        defaultAccountNumber: 'accountNumber',
        defaultIban: 'iban',
        defaultSwiftCode: 'swiftCode'
    };

    // Mapping of crypto currency codes to display names
    const currencyNames = {
        btc: 'Bitcoin',
        bch: 'Bitcoin Cash',
        eth: 'Ethereum',
        ltc: 'Litecoin',
        usdt: 'Tether',
        usdc: 'USD Coin'
    };

    // Supported networks for each crypto currency
    const networksByCurrency = {
        btc: ['Bitcoin'],
        bch: ['BCH'],
        eth: ['ERC20'],
        ltc: ['Litecoin'],
        usdt: ['ERC20', 'BEP20', 'TRC20'],
        usdc: ['ERC20']
    };

    function syncBankAccountToWithdraw(force = false) {
        const src = data.formData['bankAccountForm'] || {};
        data.formData['bankWithdrawForm'] = data.formData['bankWithdrawForm'] || {};
        Object.entries(bankFieldMap).forEach(([from, to]) => {
            const val = src[from];
            if (val !== undefined && (force || !data.formData['bankWithdrawForm'][to])) {
                data.formData['bankWithdrawForm'][to] = val;
                $(`#bankWithdrawForm #${to}`).val(val);
            }
        });
    }

['profileEditForm','bankDepositForm','cardDepositForm','cryptoDepositForm','bankWithdrawForm','cryptoWithdrawForm','paypalWithdrawForm','bankAccountForm','changePasswordForm','changeProfilePicForm','addWalletForm'].forEach(populateForm);

    syncBankAccountToWithdraw();

    renderWalletTable();
    // ======================== تعبئة البيانات الشخصية ========================
$.each(data.personalData || {}, function (id, value) {
    if (id === "passwordStrengthBar") {
        // Existing password strength logic
        const $bar = $(`#${id}`);
        $bar.css("width", value);
        const widthVal = parseInt(value, 10);
        let bgColorClass = "bg-danger";
        if (widthVal >= 70) bgColorClass = "bg-success";
        else if (widthVal >= 40) bgColorClass = "bg-warning";
        $bar.removeClass("bg-success bg-warning bg-danger").addClass(bgColorClass);
    } 
    else if (id === "compteverifie") {
        // Conditional display logic for "Vérifié" badge
        const showBadge = data.personalData.compteverifie01 === "1";
        if (showBadge) {
            $(`#${id}`).text(value).show(); // Show badge
        } else {
            $(`#${id}`).hide(); // Hide badge
        }
    } 
    else {
        const $el = $(`#${id}`);
        if ($el.is(':checkbox')) {
            $el.prop('checked', value === '1' || value === true);
        } else if ($el.is('input, textarea, select')) {
            $el.val(value);
        } else {
            $el.text(value);
        }
        const $input = $(`#${id}Input`);
        if ($input.length) {
            if ($input.is(':checkbox')) {
                $input.prop('checked', value === '1' || value === true);
            } else {
                $input.val(value);
            }
        }

    }
});
    const nameValInit = data.personalData.fullName || '';
    $('#fullNameHeader, #nameincompte').text(nameValInit);
    $('#firstname').text(nameValInit.split(' ')[0] || nameValInit);
    updateBalances();
    // ======================== عرض الإشعارات ========================
    const $notifications = $('#notifications');
    if (data.notifications?.length > 0) {
        data.notifications.forEach(n => {
            $notifications.append(`
                <div class="alert ${n.alertClass}">
                    <strong>${n.title}</strong>
                    <p class="mb-0">${n.message}</p>
                    <small>${n.time}</small>
                </div>`);
        });
    } else {
        $notifications.html('<p>Aucune notification disponible.</p>');
    }
    // ======================== عرض جدول المعاملات ========================
    const $tbody = $('#transactionsRecents');
    if (data.transactions?.length > 0) {
        data.transactions.forEach(t => {
            $tbody.append(`
                <tr>
                    <td>${t.operationNumber}</td>
                    <td>${t.type}</td>
                    <td>${t.amount}</td>
                    <td>${t.date}</td>
                    <td><span class="badge ${t.statusClass}">${t.status}</span></td>
                </tr>`);
        });
    } else {
        $tbody.html('<tr><td colspan="5" class="text-center">Aucune donnée disponible</td></tr>');
    }
    // ======================== عرض قائمة الإشعارات المنسدلة ========================
    const notificationIcons = {
        "info": "fas fa-chart-line text-primary",
        "success": "fas fa-money-bill-wave text-success",
        "warning": "fas fa-exclamation-triangle text-warning",
        "error": "fas fa-times-circle text-danger",
        "kyc": "fas fa-user-check text-info",
        "default": "fas fa-bell text-secondary"
    };
    function generateNotificationDropdownItems(notifications) {
        return notifications.map(notification => {
            const iconClass = notificationIcons[notification.type] || notificationIcons.default;
            return `
                <li>
                    <a class="dropdown-item" href="#">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="${iconClass}"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold">${notification.title}</div>
                                <div class="small text-muted">${notification.message}</div>
                                <div class="small text-muted">${notification.time}</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>`;
        }).join('');
    }


        const notifications = data.notifications || [];
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

    // ======================== نموذج تعديل الملف الشخصي ========================
    $('#editProfileBtn').on('click', function () {
        $('#ProfilInfo').hide();
        $('#ProfilEditForm').show();
    });
    $('#saveProfileBtn').on('click', function () {
        saveForm('profileEditForm');
        const nameVal = $('#fullNameInput').val();
        data.personalData.fullName = nameVal;
        data.personalData.emailaddress = $('#email').val();
        data.personalData.phone = $('#phoneInput').val();
        data.personalData.dob = $('#birthdate').val();
        data.personalData.nationality = $('#nationalityInput').val();
        data.personalData.address = $('#addressInput').val();
        saveData();
        $('#fullName').text(nameVal);
        $('#fullNameHeader').text(nameVal);
        $('#nameincompte').text(nameVal);
        $('#firstname').text(nameVal.split(' ')[0] || nameVal);
        $('#emailaddress').text(data.personalData.emailaddress);
        $('#phone').text(data.personalData.phone);
        $('#dob').text(data.personalData.dob);
        $('#nationality').text(data.personalData.nationality);
        $('#address').text(data.personalData.address);
        $('#ProfilInfo').show();
        $('#ProfilEditForm').hide();
    });
    $('#cancelEditBtn').on('click', function () {
        populateForm('profileEditForm');
        $('#ProfilInfo').show();
        $('#ProfilEditForm').hide();
    });

    $('#parametresNotifications input[type="checkbox"]').on('change', function () {
        const key = this.id;
        data.personalData[key] = this.checked ? '1' : '0';
        saveData();
    });

    $('#twoFactorAuth').on('change', function () {
        data.personalData.twoFactorAuth = this.checked ? '1' : '0';
        saveData();
    });

    function computePasswordStrength(pwd) {
        let score = 0;
        if (pwd.length >= 6) score += 5;
        if (pwd.length >= 8) score += 30;
        if (/[A-Z]/.test(pwd)) score += 20;
        if (/[0-9]/.test(pwd)) score += 20;
        if (/[^A-Za-z0-9]/.test(pwd)) score += 30;
        return Math.min(score, 100);
    }

    async function hashPassword(pwd) {
        const enc = new TextEncoder().encode(pwd);
        const buffer = await crypto.subtle.digest('SHA-256', enc);
        return Array.from(new Uint8Array(buffer)).map(b => b.toString(16).padStart(2, '0')).join('');
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
        if (currentHash !== data.personalData.passwordHash) {
            alert('Mot de passe actuel incorrect');
            return;
        }
        if (newPw !== confirm) {
            alert('Les nouveaux mots de passe ne correspondent pas.');
            return;
        }
        const newHash = await hashPassword(newPw);
        const score = computePasswordStrength(newPw);
        const label = strengthLabel(score);
        const cls = barClass(score);

        $.ajax({
            url: 'change_password.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                currentHash: currentHash,
                newHash: newHash,
                passwordStrength: label,
                passwordStrengthBar: score + '%'
            })
        }).done(function (resp) {
            if (resp && resp.success) {
                data.personalData.passwordHash = newHash;
                data.personalData.passwordStrength = label;
                data.personalData.passwordStrengthBar = score + '%';

                $('#passwordStrength')
                    .text(label)
                    .removeClass('bg-success bg-warning bg-danger')
                    .addClass(cls);
                $('#passwordStrengthBar')
                    .css('width', score + '%')
                    .attr('aria-valuenow', score)
                    .removeClass('bg-success bg-warning bg-danger')
                    .addClass(cls);

                saveData();
                $('#changePasswordModal').modal('hide');
                $('#changePasswordForm')[0].reset();
            } else {
                alert((resp && resp.error) || 'Erreur lors de la mise \u00e0 jour du mot de passe');
            }
        });
    });

$('#bankDepositForm, #cardDepositForm, #cryptoDepositForm, #bankWithdrawForm, #cryptoWithdrawForm, #paypalWithdrawForm, #bankAccountForm, #changePasswordForm, #changeProfilePicForm').on('submit', function (e) {
    e.preventDefault();
    saveForm(this.id);
    if (this.id === 'bankAccountForm') {
        syncBankAccountToWithdraw(true);
    }

        const today = new Date().toISOString().split('T')[0].replace(/-/g, '/');

        if (['bankWithdrawForm','cryptoWithdrawForm','paypalWithdrawForm'].includes(this.id)) {
            const amountField = {
                bankWithdrawForm: '#withdrawAmount',
                cryptoWithdrawForm: '#cryptoWithdrawAmount',
                paypalWithdrawForm: '#paypalWithdrawAmount'
            }[this.id];
            const amt = parseFloat($(amountField).val());
            if (!isNaN(amt) && amt > 0) {
                const cur = parseDollar(data.personalData.balance);
                data.personalData.balance = formatDollar(cur - amt);
                const method = this.id === 'bankWithdrawForm' ? 'Banque' :
                    this.id === 'paypalWithdrawForm' ? 'Paypal' :
                    (currencyNames[$('#cryptoCurrencyWithdraw').val()] || 'Crypto');
                data.retraits = data.retraits || [];
                data.retraits.unshift({
                    date: today,
                    amount: formatDollar(amt),
                    method,
                    status: 'En cours',
                    statusClass: 'bg-warning'
                });
                updateBalances();
                renderWithdrawHistory();
                showBootstrapAlert('withdrawAlert', 'Votre demande sera traitée dans les plus brefs délais.', 'success');
            }
        } else if (['bankDepositForm','cardDepositForm','cryptoDepositForm'].includes(this.id)) {
            const amountField = {
                bankDepositForm: '#bankDepositAmount',
                cardDepositForm: '#cardDepositAmount',
                cryptoDepositForm: '#cryptoAmount'
            }[this.id];
            const amt = parseFloat($(amountField).val());
            if (!isNaN(amt) && amt > 0) {
                const cur = parseDollar(data.personalData.balance);
                data.personalData.balance = formatDollar(cur + amt);
                const method = this.id === 'bankDepositForm' ? 'Banque' :
                    this.id === 'cardDepositForm' ? 'Carte' :
                    (currencyNames[$('#cryptoCurrency').val()] || 'Crypto');
                data.deposits = data.deposits || [];
                data.deposits.unshift({
                    date: today,
                    amount: formatDollar(amt),
                    method,
                    status: 'En cours',
                    statusClass: 'bg-warning'
                });
                renderDepositHistory();
                updateBalances();
                showBootstrapAlert('depositAlert', 'Votre demande sera traitée dans les plus brefs délais.', 'success');
            }
        }

        saveData();
    });
    // ======================== رفع الصورة عبر السحب أو الضغط ========================
    $('.upload-area').each(function () {
        const $area = $(this);
        const $input = $area.find('input[type="file"]');
        const displayFile = (fileName) => {
            $area.html(`
                <i class="fas fa-file-alt fa-3x mb-3"></i>
                <h5>${fileName}</h5>
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
    // ======================== تفعيل التبويبات ========================
    $('.nav-link').each(function () {
        const tabTrigger = new bootstrap.Tab(this);
        $(this).on('click', function (e) {
            e.preventDefault();
            tabTrigger.show();
        });
    });

    // ======================== Gestion des portefeuilles ========================

    function populateNetworks() {
        const currency = $('#walletCurrency').val();
        const $net = $('#walletNetwork');
        $net.empty().append('<option value="">-- Choisissez le réseau --</option>');
        (networksByCurrency[currency] || []).forEach(n => {
            $net.append(`<option value="${n}">${n}</option>`);
        });
    }
    $('#walletCurrency').on('change', populateNetworks);

    function updateCryptoDepositAddress() {
        const currency = $('#cryptoCurrency').val();
        const key = currency + 'Address';
        $('#cryptoDepositAddress').val(data.personalData[key] || '');
    }

    $('#cryptoCurrency').on('change', updateCryptoDepositAddress);
    $('#cryptoDepositAddress').on('input', function () {
        const currency = $('#cryptoCurrency').val();
        const key = currency + 'Address';
        data.personalData[key] = $(this).val();
        saveData();
    });
    updateCryptoDepositAddress();

    function renderWalletTable() {
        const $tbody = $('#walletTableBody');
        $tbody.empty();
        (data.personalData.wallets || []).forEach(w => {
            const row = `<tr data-id="${w.id}">
                <td>${currencyNames[w.currency] || w.currency}</td>
                <td>${w.network}</td>
                <td class="wallet-address">${w.address || '---'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1 wallet-edit" data-id="${w.id}"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger wallet-delete" data-id="${w.id}"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
            $tbody.append(row);
        });
    }

    $(document).on('click', '.wallet-delete', function () {
        const id = $(this).data('id');
        if (confirm('Êtes-vous sûr de vouloir supprimer cette adresse ?')) {
            data.personalData.wallets = (data.personalData.wallets || []).filter(w => w.id !== id);
            saveData();
            renderWalletTable();
        }
    });

    $(document).on('click', '.wallet-edit', function () {
        const id = $(this).data('id');
        const wallet = (data.personalData.wallets || []).find(w => w.id === id);
        if (!wallet) return;
        const newAddr = prompt('Entrez la nouvelle adresse', wallet.address || '');
        if (newAddr !== null) {
            wallet.address = newAddr;
            saveData();
            renderWalletTable();
        }
    });

    $('#addWalletBtn').on('click', function () {
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
        data.personalData.wallets = data.personalData.wallets || [];
        data.personalData.wallets.push(wallet);
        saveForm('addWalletForm');
        saveData();
        renderWalletTable();
        $('#addWalletModal').modal('hide');
        $('#walletCurrency').val('');
        populateNetworks();
        $('#walletAddressNew').val('');
        $('#walletLabel').val('');
    });
    // ======================== Gestion des historiques ========================
    function renderDepositHistory() {
        const $tbodyDeposits = $('#historiqueDepots');
        $tbodyDeposits.empty();
        if (data.deposits?.length > 0) {
            data.deposits.forEach(d => {
                $tbodyDeposits.append(`
                    <tr>
                        <td>${d.date}</td>
                        <td>${d.amount}</td>
                        <td>${d.method}</td>
                        <td><span class="badge ${d.statusClass}">${d.status}</span></td>
                    </tr>`);
            });
        } else {
            $tbodyDeposits.html('<tr><td colspan="4" class="text-center">Aucune donnée disponible</td></tr>');
        }
    }

    function renderWithdrawHistory() {
        const $tbodyRetraits = $('#historiqueRetraits');
        $tbodyRetraits.empty();
        if (data.retraits?.length > 0) {
            data.retraits.forEach(r => {
                $tbodyRetraits.append(`
                    <tr>
                        <td>${r.date}</td>
                        <td>${r.amount}</td>
                        <td>${r.method}</td>
                        <td><span class="badge ${r.statusClass}">${r.status}</span></td>
                    </tr>`);
            });
        } else {
            $tbodyRetraits.html('<tr><td colspan="4" class="text-center">Aucune donnée disponible</td></tr>');
        }
    }

    renderDepositHistory();
    renderWithdrawHistory();
	
    
    // ======================== Gestion du trading ========================

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
        if (data.tradingHistory?.length > 0) {
            data.tradingHistory.forEach(trade => {
                $tbodyTrading.append(`
                    <tr>
                        <td>${trade.temps}</td>
                        <td>${trade.paireDevises}</td>
                        <td><span class="badge ${trade.statutTypeClass}">${trade.type}</span></td>
                        <td>${trade.montant}</td>
                        <td>${trade.prix}</td>
                        <td><span class="badge ${trade.statutClass}">${trade.statut}</span></td>
                        <td class="${trade.profitClass || ''}">${trade.profitPerte}</td>
                    </tr>`);
            });
        } else {
            $tbodyTrading.html('<tr><td colspan="7" class="text-center">Aucune donnée disponible</td></tr>');
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
        data.tradingHistory = data.tradingHistory || [];
        data.tradingHistory.unshift(order);
        saveData();
        renderTradingHistory();
    }

    function finalizeOrder(order, exitPrice) {
        const priceValue = parseFloat(order.prix.replace('$', ''));
        const qty = parseFloat(order.montant.replace('$', ''));
        let profit = 0;
        if (order.type === 'Acheter') {
            profit = (exitPrice - priceValue) * qty;
        } else {
            profit = (priceValue - exitPrice) * qty;
        }
        order.profitPerte = (profit >= 0 ? '+' : '') + profit.toFixed(2) + '$';
        order.profitClass = profit >= 0 ? 'text-success' : 'text-danger';
        order.statut = 'complet';
        order.statutClass = 'bg-success';
        if (order.ocoId) {
            data.tradingHistory.forEach(o => {
                if (o !== order && o.ocoId === order.ocoId && o.statut === 'En cours') {
                    o.statut = 'annulé';
                    o.statutClass = 'bg-secondary';
                }
            });
        }
        const invested = order.invested || priceValue * qty;
        let balance = parseDollar(data.personalData.balance);
        balance += invested + profit;
        data.personalData.balance = formatDollar(balance);
        saveData();
        updateBalances();
        renderTradingHistory();
    }

    function completeOrder(order) {
        setTimeout(() => {
            finalizeOrder(order, currentPrice);
        }, 1500);
    }

    function checkStopLosses() {
        if (!data.tradingHistory) return;
        data.tradingHistory.forEach(order => {
            if (order.statut !== 'En cours' || !order.stopLoss) {
                return;
            }
            const sl = order.stopLoss;
            const entryPrice = parseFloat(order.prix.replace('$', ''));
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
                saveData();
            }
        });
    }

    function checkStopLimitOrders() {
        if (!data.tradingHistory) return;
        data.tradingHistory.forEach(order => {
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
        if (!data.tradingHistory) return;
        data.tradingHistory.forEach(order => {
            if (order.statut !== 'En cours' || order.takeProfit == null) return;
            if ((order.type === 'Acheter' && currentPrice >= order.takeProfit) ||
                (order.type === 'Vendre' && currentPrice <= order.takeProfit)) {
                finalizeOrder(order, order.takeProfit);
            }
        });
    }

    function checkStopOrders() {
        if (!data.tradingHistory) return;
        data.tradingHistory.forEach(order => {
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

    // Initialize stop loss sections visibility
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
        if (cost > parseDollar(data.personalData.balance)) {
            alert('Solde insuffisant');
            return;
        }
        let newBalance = parseDollar(data.personalData.balance) - cost;
        data.personalData.balance = formatDollar(newBalance);
        saveData();
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
            montant: '$' + amount,
            prix: '$' + price,
            statut: 'En cours',
            statutClass: 'bg-warning',
            profitPerte: '-',
            profitClass: '',
            stopLoss: stopLoss,
            stopLimit: orderType === 'stoplimit' ? { stopPrice: stopPrice, limitPrice: price } : null,
            stopPrice: orderType === 'stop' ? stopPrice : null,
            takeProfit: ocoEnabled ? takeProfit : null,
            ocoId: ocoId,
            invested: cost
        };
        if (ocoEnabled) {
            const tpOrder = Object.assign({}, order, { stopLoss: null, takeProfit: takeProfit, prix: '$' + takeProfit });
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

    // Initial load
    fetchPrice($('#currencyPair').val());
    // Poll the market data frequently to mimic real-time monitoring
    // as described in the developer overview (every second)
    setInterval(() => fetchPrice($('#currencyPair').val()), 1000);
    renderTradingHistory();

    // ======================== Historique des connexions ========================
    const $loginHistoryBody = $('#loginHistoryBody');
    if (data.loginHistory?.length > 0) {
        data.loginHistory.slice(0, 5).forEach(h => {
            $loginHistoryBody.append(`
                <tr>
                    <td>${h.date}</td>
                    <td>${h.ip}</td>
                    <td>${h.device}</td>
                </tr>`);
        });
    } else {
        $loginHistoryBody.html('<tr><td colspan="3" class="text-center">Aucune donnée disponible</td></tr>');
    }
});
