/* ============================================
   TemplateMo 3D Glassmorphism Dashboard
   https://templatemo.com
   JavaScript
============================================ */

(function() {
    'use strict';

    // ============================================
    // Theme Toggle
    // ============================================
    function initThemeToggle() {
        const themeToggle = document.getElementById('theme-toggle');
        if (!themeToggle) return;

        const iconSun = themeToggle.querySelector('.icon-sun');
        const iconMoon = themeToggle.querySelector('.icon-moon');
        
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            
            if (iconSun && iconMoon) {
                if (theme === 'light') {
                    iconSun.style.display = 'none';
                    iconMoon.style.display = 'block';
                } else {
                    iconSun.style.display = 'block';
                    iconMoon.style.display = 'none';
                }
            }
        }
        
        // Check for saved theme preference or default to dark
        const savedTheme = localStorage.getItem('theme') || 'dark';
        setTheme(savedTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            setTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });
    }

    // ============================================
    // 3D Tilt Effect
    // ============================================
    function initTiltEffect() {
        document.querySelectorAll('.glass-card-3d').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
            });
        });
    }

    // ============================================
    // Animated Counters
    // ============================================
    function animateCounter(element, target, duration = 2000) {
        const start = 0;
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (target - start) * easeOut);
            
            if (element.dataset.prefix) {
                element.textContent = element.dataset.prefix + current.toLocaleString() + (element.dataset.suffix || '');
            } else {
                element.textContent = current.toLocaleString() + (element.dataset.suffix || '');
            }
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }

    function initCounters() {
        const counters = document.querySelectorAll('.stat-value');
        counters.forEach(counter => {
            const text = counter.textContent;
            const value = parseInt(text.replace(/[^0-9]/g, ''));
            
            if (text.includes('$')) {
                counter.dataset.prefix = '$';
            }
            if (text.includes('%')) {
                counter.dataset.suffix = '%';
            }
            
            animateCounter(counter, value);
        });
    }

    // ============================================
    // Dashboard Data (Dynamic)
    // ============================================
    function getDefaultDashboardData() {
        return {
            stats: [
                { key: 'revenue', title: 'Total Revenue', value: 84254, prefix: '$', change: '+12.5%', changeType: 'positive' },
                { key: 'users', title: 'Active Users', value: 24521, change: '+8.2%', changeType: 'positive' },
                { key: 'orders', title: 'Total Orders', value: 8461, change: '-3.1%', changeType: 'negative' },
                { key: 'conversion', title: 'Conversion Rate', value: 3.24, suffix: '%', change: '+2.4%', changeType: 'positive' }
            ],
            chart: {
                title: 'Revenue Analytics',
                subtitle: 'Monthly revenue overview',
                bars: [
                    { label: 'Jan', height: 120, colorClass: 'bar-emerald' },
                    { label: 'Feb', height: 160, colorClass: 'bar-gold' },
                    { label: 'Mar', height: 90, colorClass: 'bar-coral' },
                    { label: 'Apr', height: 140, colorClass: 'bar-teal' },
                    { label: 'May', height: 180, colorClass: 'bar-amber' },
                    { label: 'Jun', height: 130, colorClass: 'bar-emerald' },
                    { label: 'Jul', height: 170, colorClass: 'bar-gold' },
                    { label: 'Aug', height: 150, colorClass: 'bar-coral' },
                    { label: 'Sep', height: 190, colorClass: 'bar-teal' },
                    { label: 'Oct', height: 140, colorClass: 'bar-amber' },
                    { label: 'Nov', height: 175, colorClass: 'bar-emerald' },
                    { label: 'Dec', height: 200, colorClass: 'bar-gold' }
                ]
            },
            activity: [
                { initials: 'JD', gradient: 'linear-gradient(135deg, var(--emerald-light), var(--emerald))', name: 'John Doe', action: 'purchased Premium Plan', time: '2 minutes ago' },
                { initials: 'AS', gradient: 'linear-gradient(135deg, var(--gold), var(--amber))', name: 'Anna Smith', action: 'submitted a support ticket', time: '15 minutes ago' },
                { initials: 'MJ', gradient: 'linear-gradient(135deg, var(--coral), var(--gold))', name: 'Mike Johnson', action: 'upgraded subscription', time: '1 hour ago' },
                { initials: 'EW', gradient: 'linear-gradient(135deg, var(--success), var(--emerald))', name: 'Emily White', action: 'completed onboarding', time: '2 hours ago' },
                { initials: 'RB', gradient: 'linear-gradient(135deg, var(--warning), var(--gold))', name: 'Robert Brown', action: 'requested refund', time: '3 hours ago' }
            ],
            transactions: [
                {
                    customer: { name: 'John Doe', email: 'john@example.com', initials: 'JD', gradient: 'linear-gradient(135deg, var(--emerald-light), var(--emerald))' },
                    product: 'Premium Plan',
                    date: 'Jan 15, 2025',
                    status: 'completed',
                    statusLabel: 'Completed',
                    amount: '$299.00'
                },
                {
                    customer: { name: 'Anna Smith', email: 'anna@example.com', initials: 'AS', gradient: 'linear-gradient(135deg, var(--gold), var(--amber))' },
                    product: 'Enterprise License',
                    date: 'Jan 14, 2025',
                    status: 'processing',
                    statusLabel: 'Processing',
                    amount: '$1,499.00'
                },
                {
                    customer: { name: 'Mike Johnson', email: 'mike@example.com', initials: 'MJ', gradient: 'linear-gradient(135deg, var(--success), var(--emerald))' },
                    product: 'Team Bundle',
                    date: 'Jan 13, 2025',
                    status: 'completed',
                    statusLabel: 'Completed',
                    amount: '$599.00'
                },
                {
                    customer: { name: 'Emily White', email: 'emily@example.com', initials: 'EW', gradient: 'linear-gradient(135deg, var(--coral), var(--gold))' },
                    product: 'Starter Plan',
                    date: 'Jan 12, 2025',
                    status: 'pending',
                    statusLabel: 'Pending',
                    amount: '$49.00'
                },
                {
                    customer: { name: 'Robert Brown', email: 'robert@example.com', initials: 'RB', gradient: 'linear-gradient(135deg, var(--emerald), var(--gold))' },
                    product: 'Pro Annual',
                    date: 'Jan 11, 2025',
                    status: 'completed',
                    statusLabel: 'Completed',
                    amount: '$199.00'
                }
            ],
            traffic: {
                visitors: '24.5K',
                label: 'Visitors',
                segments: [
                    { color: 'var(--emerald-light)', percentage: 50 },
                    { color: 'var(--gold)', percentage: 30 },
                    { color: 'var(--coral)', percentage: 20 }
                ],
                legend: [
                    { colorClass: 'cyan', label: 'Organic Search', percent: 50 },
                    { colorClass: 'magenta', label: 'Social Media', percent: 30 },
                    { colorClass: 'purple', label: 'Direct Traffic', percent: 20 }
                ]
            },
            progress: [
                { label: 'UI Design', value: 85, colorClass: 'cyan' },
                { label: 'Backend API', value: 62, colorClass: 'magenta' },
                { label: 'Testing', value: 45, colorClass: 'purple' },
                { label: 'Documentation', value: 28, colorClass: 'cyan' }
            ]
        };
    }

    function formatStatValue(stat) {
        if (typeof stat.value === 'number') {
            const formatted = stat.value.toLocaleString(undefined, { maximumFractionDigits: 2 });
            return `${stat.prefix || ''}${formatted}${stat.suffix || ''}`;
        }
        return stat.value || '';
    }

    function renderStats(stats) {
        stats.forEach(stat => {
            const card = document.querySelector(`[data-stat-key="${stat.key}"]`);
            if (!card) return;
            const title = card.querySelector('[data-stat-title]');
            const value = card.querySelector('[data-stat-value]');
            const change = card.querySelector('[data-stat-change]');
            const changeContainer = card.querySelector('.stat-change');

            if (title) title.textContent = stat.title;
            if (value) value.textContent = formatStatValue(stat);
            if (change) change.textContent = stat.change;
            if (changeContainer) {
                changeContainer.classList.remove('positive', 'negative');
                if (stat.changeType) {
                    changeContainer.classList.add(stat.changeType);
                }
            }
        });
    }

    function renderChart(chart) {
        const title = document.querySelector('[data-chart-title]');
        const subtitle = document.querySelector('[data-chart-subtitle]');
        const barsContainer = document.querySelector('[data-chart-bars]');

        if (title) title.textContent = chart.title;
        if (subtitle) subtitle.textContent = chart.subtitle;
        if (!barsContainer) return;

        barsContainer.innerHTML = '';
        chart.bars.forEach(bar => {
            const group = document.createElement('div');
            group.className = 'chart-bar-group';

            const barElement = document.createElement('div');
            barElement.className = `chart-bar ${bar.colorClass || ''}`.trim();
            barElement.style.height = `${bar.height}px`;

            const label = document.createElement('span');
            label.className = 'chart-label';
            label.textContent = bar.label;

            group.appendChild(barElement);
            group.appendChild(label);
            barsContainer.appendChild(group);
        });
    }

    function renderActivity(items) {
        const list = document.querySelector('[data-activity-list]');
        if (!list) return;
        list.innerHTML = '';
        items.forEach(item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'activity-item';

            const avatar = document.createElement('div');
            avatar.className = 'activity-avatar';
            avatar.style.background = item.gradient;
            avatar.textContent = item.initials;

            const content = document.createElement('div');
            content.className = 'activity-content';

            const text = document.createElement('p');
            text.className = 'activity-text';
            text.innerHTML = `<strong>${item.name}</strong> ${item.action}`;

            const time = document.createElement('span');
            time.className = 'activity-time';
            time.textContent = item.time;

            content.appendChild(text);
            content.appendChild(time);
            wrapper.appendChild(avatar);
            wrapper.appendChild(content);
            list.appendChild(wrapper);
        });
    }

    function renderTransactions(items) {
        const tbody = document.querySelector('[data-transaction-rows]');
        if (!tbody) return;
        tbody.innerHTML = '';
        items.forEach(item => {
            const row = document.createElement('tr');

            const customerCell = document.createElement('td');
            const userWrapper = document.createElement('div');
            userWrapper.className = 'table-user';

            const avatar = document.createElement('div');
            avatar.className = 'table-avatar';
            avatar.style.background = item.customer.gradient;
            avatar.textContent = item.customer.initials;

            const info = document.createElement('div');
            info.className = 'table-user-info';

            const name = document.createElement('span');
            name.className = 'table-user-name';
            name.textContent = item.customer.name;

            const email = document.createElement('span');
            email.className = 'table-user-email';
            email.textContent = item.customer.email;

            info.appendChild(name);
            info.appendChild(email);
            userWrapper.appendChild(avatar);
            userWrapper.appendChild(info);
            customerCell.appendChild(userWrapper);

            const productCell = document.createElement('td');
            productCell.textContent = item.product;

            const dateCell = document.createElement('td');
            dateCell.textContent = item.date;

            const statusCell = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = `status-badge ${item.status}`;
            badge.textContent = item.statusLabel || item.status;
            statusCell.appendChild(badge);

            const amountCell = document.createElement('td');
            const amount = document.createElement('span');
            amount.className = 'table-amount';
            amount.textContent = item.amount;
            amountCell.appendChild(amount);

            row.appendChild(customerCell);
            row.appendChild(productCell);
            row.appendChild(dateCell);
            row.appendChild(statusCell);
            row.appendChild(amountCell);

            tbody.appendChild(row);
        });
    }

    function renderTraffic(traffic) {
        const value = document.querySelector('[data-traffic-value]');
        const label = document.querySelector('[data-traffic-label]');
        const legend = document.querySelector('[data-traffic-legend]');
        const svg = document.querySelector('[data-traffic-svg]');

        if (value) value.textContent = traffic.visitors;
        if (label) label.textContent = traffic.label;

        if (legend) {
            legend.innerHTML = '';
            traffic.legend.forEach(item => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.innerHTML = `<span class="legend-color ${item.colorClass}"></span><span>${item.label} (${item.percent}%)</span>`;
                legend.appendChild(legendItem);
            });
        }

        if (svg) {
            const radius = 54;
            const circumference = 2 * Math.PI * radius;
            let offset = 0;

            svg.innerHTML = '';
            const background = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            background.setAttribute('class', 'donut-bg');
            background.setAttribute('cx', '70');
            background.setAttribute('cy', '70');
            background.setAttribute('r', String(radius));
            svg.appendChild(background);

            traffic.segments.forEach(segment => {
                const length = (segment.percentage / 100) * circumference;
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('class', 'donut-segment');
                circle.setAttribute('cx', '70');
                circle.setAttribute('cy', '70');
                circle.setAttribute('r', String(radius));
                circle.setAttribute('stroke', segment.color);
                circle.setAttribute('stroke-dasharray', `${length} ${circumference}`);
                circle.setAttribute('stroke-dashoffset', `${-offset}`);
                svg.appendChild(circle);
                offset += length;
            });
        }
    }

    function renderProgress(items) {
        const container = document.querySelector('[data-progress-list]');
        if (!container) return;
        container.innerHTML = '';

        items.forEach(item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'progress-item';

            const header = document.createElement('div');
            header.className = 'progress-header';
            header.innerHTML = `<span class="progress-label">${item.label}</span><span class="progress-value">${item.value}%</span>`;

            const bar = document.createElement('div');
            bar.className = 'progress-bar';
            const fill = document.createElement('div');
            fill.className = `progress-fill ${item.colorClass}`.trim();
            fill.style.width = `${item.value}%`;
            bar.appendChild(fill);

            wrapper.appendChild(header);
            wrapper.appendChild(bar);
            container.appendChild(wrapper);
        });
    }

    async function initDashboardData() {
        const dashboard = document.querySelector('[data-dashboard="overview"]');
        if (!dashboard) return null;

        const source = dashboard.dataset.source;
        let data = getDefaultDashboardData();

        if (source) {
            try {
                const response = await fetch(source, { cache: 'no-store' });
                if (response.ok) {
                    data = await response.json();
                }
            } catch (error) {
                console.warn('Unable to load dashboard data.', error);
            }
        }

        if (data.stats) renderStats(data.stats);
        if (data.chart) renderChart(data.chart);
        if (data.activity) renderActivity(data.activity);
        if (data.transactions) renderTransactions(data.transactions);
        if (data.traffic) renderTraffic(data.traffic);
        if (data.progress) renderProgress(data.progress);

        return data;
    }

    // ============================================
    // Mobile Menu Toggle
    // ============================================
    function initMobileMenu() {
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', (e) => {
                if (sidebar.classList.contains('open') && 
                    !sidebar.contains(e.target) && 
                    !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            });
        }
    }

    // ============================================
    // Form Validation (for login/register)
    // ============================================
    function initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                let isValid = true;
                const inputs = form.querySelectorAll('.form-input[required]');
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        isValid = false;
                        input.style.borderColor = '#ff6b6b';
                    } else {
                        input.style.borderColor = '';
                    }
                });

                // Email validation
                const emailInput = form.querySelector('input[type="email"]');
                if (emailInput && emailInput.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailInput.value)) {
                        isValid = false;
                        emailInput.style.borderColor = '#ff6b6b';
                    }
                }

                if (isValid) {
                    // Form is valid - you can add your submission logic here
                    console.log('Form is valid');
                    // For demo purposes, redirect to dashboard
                    if (form.dataset.redirect) {
                        window.location.href = form.dataset.redirect;
                    }
                }
            });
        });
    }

    // ============================================
    // Password Visibility Toggle
    // ============================================
    function initPasswordToggle() {
        const toggleButtons = document.querySelectorAll('.password-toggle');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', () => {
                const input = button.parentElement.querySelector('input');
                const icon = button.querySelector('svg');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
                } else {
                    input.type = 'password';
                    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
                }
            });
        });
    }

    // ============================================
    // Smooth Page Transitions
    // ============================================
    function initPageTransitions() {
        const links = document.querySelectorAll('a[href$=".html"]');
        
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                // Skip external links
                if (link.hostname !== window.location.hostname) return;
                
                e.preventDefault();
                const href = link.getAttribute('href');
                
                document.body.style.opacity = '0';
                document.body.style.transition = 'opacity 0.3s ease';
                
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            });
        });

        // Fade in on page load
        window.addEventListener('load', () => {
            document.body.style.opacity = '1';
        });
    }

    // ============================================
    // Settings Tab Navigation
    // ============================================
    function initSettingsTabs() {
        const tabLinks = document.querySelectorAll('.settings-nav-link[data-tab]');
        
        if (tabLinks.length === 0) return;

        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Get target tab
                const tabId = link.getAttribute('data-tab');
                
                // Remove active class from all nav links
                document.querySelectorAll('.settings-nav-link').forEach(navLink => {
                    navLink.classList.remove('active');
                });
                
                // Add active class to clicked link
                link.classList.add('active');
                
                // Hide all tab contents
                document.querySelectorAll('.settings-tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Show target tab content
                const targetTab = document.getElementById('tab-' + tabId);
                if (targetTab) {
                    targetTab.classList.add('active');
                }
            });
        });

        // Theme select sync with toggle
        const themeSelect = document.getElementById('theme-select');
        if (themeSelect) {
            const currentTheme = localStorage.getItem('theme') || 'dark';
            themeSelect.value = currentTheme;
            
            themeSelect.addEventListener('change', () => {
                const theme = themeSelect.value;
                if (theme === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                } else {
                    document.documentElement.setAttribute('data-theme', theme);
                    localStorage.setItem('theme', theme);
                }
                
                // Update theme toggle icons
                const iconSun = document.querySelector('#theme-toggle .icon-sun');
                const iconMoon = document.querySelector('#theme-toggle .icon-moon');
                if (iconSun && iconMoon) {
                    const effectiveTheme = document.documentElement.getAttribute('data-theme');
                    if (effectiveTheme === 'light') {
                        iconSun.style.display = 'none';
                        iconMoon.style.display = 'block';
                    } else {
                        iconSun.style.display = 'block';
                        iconMoon.style.display = 'none';
                    }
                }
            });
        }
    }

    // ============================================
    // Initialize All Functions
    // ============================================
    function init() {
        initThemeToggle();
        initTiltEffect();
        initMobileMenu();
        initFormValidation();
        initPasswordToggle();
        initPageTransitions();
        initSettingsTabs();
        const dashboardPromise = initDashboardData();
        if (dashboardPromise && typeof dashboardPromise.then === 'function') {
            dashboardPromise.then(() => initCounters());
        } else {
            initCounters();
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
