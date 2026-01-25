(function (global) {
const { numberToChinese, formatDate } = global.AppUtils;
const { calculateRepaymentPlan, getLatestCalculation } = global.AppCalculator;

const FILE_TEMPLATES = [
    {
        id: 'pawn-receipt',
        name: '典当抵押收据.docx',
        label: '《典当抵押收据.docx》',
        templatePath: 'doc/典当抵押收据.docx',
        defaultCopies: 2,
    },
    {
        id: 'notice-letter',
        name: '告知函.docx',
        label: '《告知函.docx》',
        templatePath: 'doc/告知函.docx',
        defaultCopies: 1,
    },
    {
        id: 'micro-enterprise-waiver',
        name: '小微企业免缴不动产登记费告知询问笔录.docx',
        label: '《小微企业免缴不动产登记费告知询问笔录.docx》',
        templatePath: 'doc/小微企业免缴不动产登记费告知询问笔录.docx',
        defaultCopies: 1,
    },
    {
        id: 'mortgage-contract',
        name: '房产抵押合同.docx',
        label: '《房产抵押合同.docx》',
        templatePath: 'doc/房产抵押合同.docx',
        defaultCopies: 1,
    },
    {
        id: 'ownership-statement',
        name: '房屋所有权申明.docx',
        label: '《房屋所有权申明.docx》',
        templatePath: 'doc/房屋所有权申明.docx',
        defaultCopies: 1,
    },
    {
        id: 'mortgage-archive',
        name: '留底    ----抵押合同.docx',
        label: '《留底    ----抵押合同.docx》',
        templatePath: 'doc/留底    ----抵押合同.docx',
        defaultCopies: 1,
    },
    {
        id: 'authorization-letter',
        name: '授权委托书.docx',
        label: '《授权委托书.docx》',
        templatePath: 'doc/授权委托书.docx',
        defaultCopies: 1,
    },
    {
        id: 'installment-agreement',
        name: '借款分期还款附议.docx',
        label: '《借款分期还款附议.docx》',
        templatePath: 'doc/借款分期还款附议.docx',
        defaultCopies: 2,
    },
    {
        id: 'registry-application',
        name: '不动产登记和房屋交易申请表.docx',
        label: '《不动产登记和房屋交易申请表.docx》',
        templatePath: 'doc/不动产登记和房屋交易申请表.docx',
        defaultCopies: 1,
    },
    {
        id: 'fuzhou-waiver-letter',
        name: '福州便民中心（免缴承诺书）.docx',
        label: '《福州便民中心（免缴承诺书）.docx》',
        templatePath: 'doc/福州便民中心（免缴承诺书）.docx',
        defaultCopies: 1,
    },
    {
        id: 'supplement-agreement',
        name: '补充协议范本.docx',
        label: '《补充协议范本.docx》',
        templatePath: 'doc/补充协议范本.docx',
        defaultCopies: 1,
    },
    {
        id: 'minhou-waiver-letter',
        name: '闽侯县    便民中心（免缴承诺书）.docx',
        label: '《闽侯县    便民中心（免缴承诺书）.docx》',
        templatePath: 'doc/闽侯县    便民中心（免缴承诺书）.docx',
        defaultCopies: 1,
    },
];

let modalRefs = null;
let passwordPrompt = null;

function initContractDownload() {
    const downloadBtn = document.getElementById('download-btn');
    if (!downloadBtn) {
        return;
    }

    modalRefs = createDownloadModal();
    passwordPrompt = createPasswordPrompt();
    downloadBtn.addEventListener('click', handleDownloadClick);
}

async function handleDownloadClick() {
    const calculation = calculateRepaymentPlan();
    if (!calculation) {
        return;
    }

    const password = await requestDownloadPassword();
    if (password === null) {
        return;
    }

    if (password.trim() !== 'asd123') {
        alert('密码错误，请重新输入。');
        return;
    }

    openModal();
}

function requestDownloadPassword() {
    if (!passwordPrompt) {
        passwordPrompt = createPasswordPrompt();
    }
    return passwordPrompt.open();
}

function createPasswordPrompt() {
    const container = document.createElement('div');
    container.className = 'download-modal download-password-modal';
    container.setAttribute('aria-hidden', 'true');
    container.style.display = 'none';

    const overlay = document.createElement('div');
    overlay.className = 'download-modal__overlay';

    const dialog = document.createElement('div');
    dialog.className = 'download-modal__dialog';

    const header = document.createElement('header');
    header.className = 'download-modal__header';

    const title = document.createElement('h3');
    title.textContent = '请输入下载密码';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'download-modal__close';
    closeBtn.setAttribute('aria-label', '关闭');
    closeBtn.innerHTML = '&times;';

    const body = document.createElement('div');
    body.className = 'download-modal__body';

    const form = document.createElement('form');
    const passwordFormId = 'download-password-form';
    form.id = passwordFormId;

    const fieldWrapper = document.createElement('label');
    fieldWrapper.className = 'download-modal__field';

    const fieldLabel = document.createElement('span');
    fieldLabel.textContent = '下载密码';

    const passwordInput = document.createElement('input');
    passwordInput.type = 'password';
    passwordInput.placeholder = '请输入下载密码';
    passwordInput.autocomplete = 'off';
    passwordInput.required = true;

    fieldWrapper.append(fieldLabel, passwordInput);
    form.appendChild(fieldWrapper);
    body.appendChild(form);

    const footer = document.createElement('footer');
    footer.className = 'download-modal__footer';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'secondary';
    cancelBtn.textContent = '取消';

    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'submit';
    confirmBtn.className = 'success';
    confirmBtn.textContent = '确定';
    confirmBtn.setAttribute('form', passwordFormId);

    header.append(title, closeBtn);
    footer.append(cancelBtn, confirmBtn);
    dialog.append(header, body, footer);
    container.append(overlay, dialog);
    document.body.appendChild(container);

    let resolver = null;
    let pendingPromise = null;

    const handleEsc = (event) => {
        if (event.key === 'Escape') {
            closePrompt(null);
        }
    };

    const closePrompt = (result) => {
        container.classList.remove('is-active');
        container.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', handleEsc);
        setTimeout(() => {
            if (!container.classList.contains('is-active')) {
                container.style.display = 'none';
            }
        }, 200);
        const pendingResolver = resolver;
        resolver = null;
        const value = typeof result === 'string' ? result : null;
        passwordInput.value = '';
        pendingPromise = null;
        if (pendingResolver) {
            pendingResolver(value);
        }
    };

    const openPrompt = () => {
        if (pendingPromise) {
            return pendingPromise;
        }

        pendingPromise = new Promise((resolve) => {
            resolver = resolve;
            container.style.display = 'flex';
            requestAnimationFrame(() => {
                container.classList.add('is-active');
                container.setAttribute('aria-hidden', 'false');
                passwordInput.focus();
                document.addEventListener('keydown', handleEsc);
            });
        });
        return pendingPromise;
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        closePrompt(passwordInput.value);
    });

    overlay.addEventListener('click', () => closePrompt(null));
    closeBtn.addEventListener('click', () => closePrompt(null));
    cancelBtn.addEventListener('click', () => closePrompt(null));
    dialog.addEventListener('click', (event) => event.stopPropagation());

    return {
        open: openPrompt,
        close: () => closePrompt(null),
    };
}

function createDownloadModal() {
    const container = document.createElement('div');
    container.className = 'download-modal';
    container.setAttribute('aria-hidden', 'true');
    container.style.display = 'none';

    const overlay = document.createElement('div');
    overlay.className = 'download-modal__overlay';

    const dialog = document.createElement('div');
    dialog.className = 'download-modal__dialog';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'download-modal-title');

    const header = document.createElement('header');
    header.className = 'download-modal__header';

    const title = document.createElement('h3');
    title.id = 'download-modal-title';
    title.textContent = '下载文件';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'download-modal__close';
    closeBtn.setAttribute('aria-label', '关闭');
    closeBtn.innerHTML = '&times;';

    const body = document.createElement('div');
    body.className = 'download-modal__body';

    const supplementSection = document.createElement('section');
    supplementSection.className = 'download-modal__section';

    const supplementTitle = document.createElement('h4');
    supplementTitle.textContent = '内容补充';

    const formGrid = document.createElement('div');
    formGrid.className = 'download-modal__form-grid';

    const borrowerNameField = createTextField('borrower-name', '借款人');
    const borrowerCardField = createTextField('borrower-card', '身份证号');
    const borrowerPhoneField = createTextField('borrower-phone', '借款人电话');
    const borrowerAreaField = createTextField('borrower-area', '借款人住址');
    const attributionField = createSelectField('attribution', '归宿地', [
        { value: '福州市', label: '福州市' },
        { value: '闽侯县', label: '闽侯县' },
    ], '福州市');
    const pawnTicketField = createTextField('pawn-ticket', '当票');
    const collateralField = createTextField('collateral', '抵押物');
    const collateralTypeField = createTextField('collateral-type', '抵押物类型');
    const collateralDiscountField = createTextField('collateral-discount', '折价比例（折）');
    const monthlyInterestRateField = createTextField('monthly-interest-rate', '月综合利润(%)');
    const collateral1Field = createTextField('collateral1', '房屋所有权证号');
    const collateralAreaField = createTextField('collateral-area', '建筑面积');
    const borrowerTotalField = createTextField('borrower-total', '抵押价值');

    formGrid.append(
        borrowerNameField.wrapper,
        borrowerCardField.wrapper,
        borrowerPhoneField.wrapper,
        borrowerAreaField.wrapper,
        attributionField.wrapper,
        pawnTicketField.wrapper,
        collateralField.wrapper,
        collateralTypeField.wrapper,
        collateralDiscountField.wrapper,
        monthlyInterestRateField.wrapper,
        collateral1Field.wrapper,
        collateralAreaField.wrapper,
        borrowerTotalField.wrapper,
    );

    const filesSection = document.createElement('section');
    filesSection.className = 'download-modal__section';

    const filesTitle = document.createElement('h4');
    filesTitle.textContent = '下载文件';

    const fileList = document.createElement('div');
    fileList.className = 'download-modal__file-list';

    const fileControls = FILE_TEMPLATES.map((config) => {
        const row = document.createElement('div');
        row.className = 'download-modal__file-row';

        const checkboxLabel = document.createElement('label');
        checkboxLabel.className = 'download-modal__file-checkbox';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = true;
        checkbox.dataset.templateId = config.id;

        const checkboxText = document.createElement('span');
        checkboxText.textContent = config.label;

        checkboxLabel.append(checkbox, checkboxText);

        const copiesWrapper = document.createElement('div');
        copiesWrapper.className = 'download-modal__copies';

        const copiesInput = document.createElement('input');
        copiesInput.type = 'number';
        copiesInput.min = '1';
        copiesInput.step = '1';
        copiesInput.value = String(config.defaultCopies);
        copiesInput.className = 'download-modal__copies-input';

        const copiesSuffix = document.createElement('span');
        copiesSuffix.textContent = '份';

        copiesWrapper.append(copiesInput, copiesSuffix);
        row.append(checkboxLabel, copiesWrapper);
        fileList.appendChild(row);

        return { config, checkbox, copiesInput, row };
    });

    const footer = document.createElement('footer');
    footer.className = 'download-modal__footer';

    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.className = 'download-modal__confirm';
    confirmBtn.textContent = '确定下载';

    header.append(title, closeBtn);
    supplementSection.append(supplementTitle, formGrid);
    filesSection.append(filesTitle, fileList);
    body.append(supplementSection, filesSection);
    footer.appendChild(confirmBtn);
    dialog.append(header, body, footer);
    container.append(overlay, dialog);

    document.body.appendChild(container);

    const focusTrap = () => borrowerNameField.input.focus();

    const refs = {
        container,
        overlay,
        dialog,
        closeBtn,
        confirmBtn,
        firstField: borrowerNameField.input,
        fields: {
            collateral: collateralField.input,
            collateralType: collateralTypeField.input,
            borrowerName: borrowerNameField.input,
            borrowerCard: borrowerCardField.input,
            borrowerPhone: borrowerPhoneField.input,
            borrowerArea: borrowerAreaField.input,
            attribution: attributionField.input,
            pawnTicket: pawnTicketField.input,
            collateralDiscount: collateralDiscountField.input,
            monthlyInterestRate: monthlyInterestRateField.input,
            collateral1: collateral1Field.input,
            collateralArea: collateralAreaField.input,
            borrowerTotal: borrowerTotalField.input,
        },
        fileControls,
    };

    const updateFileVisibility = (selectedAttribution) => {
        const hiddenMappings = {
            福州市: new Set(['micro-enterprise-waiver', 'minhou-waiver-letter']),
            闽侯县: new Set(['fuzhou-waiver-letter']),
        };
        const hiddenIds = hiddenMappings[selectedAttribution] || new Set();

        fileControls.forEach((control) => {
            const shouldHide = hiddenIds.has(control.config.id);
            control.row.style.display = shouldHide ? 'none' : '';
            control.row.setAttribute('aria-hidden', shouldHide ? 'true' : 'false');
            control.checkbox.disabled = shouldHide;
            control.copiesInput.disabled = shouldHide;
            if (shouldHide) {
                control.checkbox.dataset.previousChecked = control.checkbox.checked ? 'true' : 'false';
                control.checkbox.checked = false;
            } else {
                if (control.checkbox.dataset.previousChecked) {
                    control.checkbox.checked = control.checkbox.dataset.previousChecked === 'true';
                    delete control.checkbox.dataset.previousChecked;
                }
            }
        });
    };

    attributionField.input.addEventListener('change', () => {
        updateFileVisibility(attributionField.input.value);
    });
    updateFileVisibility(attributionField.input.value);

    overlay.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);
    confirmBtn.addEventListener('click', handleConfirmDownload);
    dialog.addEventListener('click', (event) => event.stopPropagation());

    refs.open = () => {
        container.style.display = 'flex';
        requestAnimationFrame(() => {
            container.classList.add('is-active');
            container.setAttribute('aria-hidden', 'false');
            focusTrap();
            document.addEventListener('keydown', handleEscKey);
        });
    };

    refs.close = () => {
        container.classList.remove('is-active');
        container.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', handleEscKey);
        setTimeout(() => {
            if (!container.classList.contains('is-active')) {
                container.style.display = 'none';
            }
        }, 200);
    };

    return refs;
}

function createTextField(id, labelText, defaultValue = '') {
    const wrapper = document.createElement('label');
    wrapper.className = 'download-modal__field';

    const label = document.createElement('span');
    label.textContent = labelText;

    const input = document.createElement('input');
    input.type = 'text';
    input.id = id;
    if (defaultValue) {
        input.value = defaultValue;
    }

    wrapper.append(label, input);

    return { wrapper, input };
}

function createSelectField(id, labelText, options, defaultValue) {
    const wrapper = document.createElement('label');
    wrapper.className = 'download-modal__field';

    const label = document.createElement('span');
    label.textContent = labelText;

    const select = document.createElement('select');
    select.id = id;

    options.forEach(({ value, label: optionLabel }) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = optionLabel;
        if (defaultValue && defaultValue === value) {
            option.selected = true;
        }
        select.appendChild(option);
    });

    wrapper.append(label, select);

    return { wrapper, input: select };
}

function openModal() {
    if (!modalRefs) {
        return;
    }

    modalRefs.open();
}

function closeModal() {
    if (!modalRefs) {
        return;
    }

    modalRefs.close();
}

function handleEscKey(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
}

function handleConfirmDownload() {
    if (!modalRefs) {
        return;
    }

    const calculation = getLatestCalculation();
    if (!calculation) {
        alert('请先计算还款计划');
        return;
    }

    const fieldValues = Object.fromEntries(
        Object.entries(modalRefs.fields).map(([key, input]) => [key, input.value.trim()])
    );

    const selections = modalRefs.fileControls
        .map(({ config, checkbox, copiesInput }) => {
            const parsedCopies = parseInt(copiesInput.value, 10);
            const copies = Number.isFinite(parsedCopies) && parsedCopies > 0
                ? parsedCopies
                : config.defaultCopies;

            if (!Number.isFinite(parsedCopies) || parsedCopies <= 0) {
                copiesInput.value = String(config.defaultCopies);
            }

            return checkbox.checked
                ? { config, copies }
                : null;
        })
        .filter(Boolean);

    if (selections.length === 0) {
        alert('请至少选择一个需要下载的文件');
        return;
    }

    closeModal();

    processDownloadQueue({
        calculation,
        borrowerName: fieldValues.borrowerName,
        borrowerCard: fieldValues.borrowerCard,
        borrowerPhone: fieldValues.borrowerPhone,
        borrowerArea: fieldValues.borrowerArea,
        attribution: fieldValues.attribution,
        pawnTicket: fieldValues.pawnTicket,
        collateral: fieldValues.collateral,
        collateralType: fieldValues.collateralType,
        collateralDiscount: fieldValues.collateralDiscount,
        monthlyInterestRate: fieldValues.monthlyInterestRate,
        collateral1: fieldValues.collateral1,
        collateralArea: fieldValues.collateralArea,
        borrowerTotal: fieldValues.borrowerTotal,
        selections,
    }).catch((error) => {
        console.error(error);
        alert('下载文件时出现错误，请稍后重试。');
    });
}

async function processDownloadQueue({
    calculation,
    borrowerName,
    borrowerCard,
    borrowerPhone,
    borrowerArea,
    attribution,
    pawnTicket,
    collateral,
    collateralType,
    collateralDiscount,
    monthlyInterestRate,
    collateral1,
    collateralArea,
    borrowerTotal,
    selections,
}) {
    if (!window.JSZip || typeof window.JSZip.loadAsync !== 'function') {
        alert('文档组件加载失败，请刷新页面后重试。');
        return;
    }

    const placeholderValues = buildTemplatePlaceholders({
        calculation,
        borrowerName,
        borrowerCard,
        borrowerPhone,
        borrowerArea,
        attribution,
        pawnTicket,
        collateral,
        collateralType,
        collateralDiscount,
        monthlyInterestRate,
        collateral1,
        collateralArea,
        borrowerTotal,
    });

    const templateCache = new Map();
    const sanitizedBorrowerName = sanitizeFileSystemName(borrowerName);
    const folderName = sanitizedBorrowerName || '合同文件';
    const downloadZip = new window.JSZip();
    const zipFolder = downloadZip.folder(folderName) || downloadZip;

    for (const { config, copies } of selections) {
        const templateBuffer = await loadTemplateBuffer(config, templateCache);
        const docBlob = await fillDocxTemplate(templateBuffer, placeholderValues);
        const docArrayBuffer = await docBlob.arrayBuffer();

        for (let index = 1; index <= copies; index += 1) {
            const baseFileName = copies > 1
                ? appendCopySuffix(config.name, index)
                : config.name;
            const prefixedFileName = (config.id === 'registry-application' && attribution)
                ? `${attribution}${baseFileName}`
                : baseFileName;
            zipFolder.file(prefixedFileName, docArrayBuffer.slice(0));
        }
    }

    const zipBlob = await downloadZip.generateAsync({ type: 'blob' });
    const zipFileName = sanitizedBorrowerName
        ? `借款人（${sanitizedBorrowerName}）.zip`
        : '借款人.zip';
    triggerFileDownload(zipBlob, zipFileName);
}

function buildTemplatePlaceholders({
    calculation,
    borrowerName,
    borrowerCard,
    borrowerPhone,
    borrowerArea,
    attribution,
    pawnTicket,
    collateral,
    collateralType,
    collateralDiscount,
    monthlyInterestRate,
    collateral1,
    collateralArea,
    borrowerTotal,
}) {
    const amount = Number.isFinite(calculation.loanAmount) ? calculation.loanAmount : 0;
    const roundedAmount = Math.round(amount * 100) / 100;
    const start = new Date(calculation.startDate.getTime());
    const due = calculation.endDate
        ? new Date(calculation.endDate.getTime())
        : computeDueDate(start, calculation.loanMonths);
    const halfYearDue = new Date(start.getTime());
    halfYearDue.setDate(halfYearDue.getDate() + 179);
    const discountInput = typeof collateralDiscount === 'string'
        ? collateralDiscount.trim()
        : '';
    const borrowerTotalNum = parseFloat(String(borrowerTotal).replace(/[^\d.-]/g, ''));
    const discountRatio = (!discountInput && Number.isFinite(borrowerTotalNum) && borrowerTotalNum > 0)
        ? (roundedAmount / borrowerTotalNum) * 10
        : null;
    const discountText = discountInput || (
        discountRatio !== null
            ? discountRatio.toFixed(2).replace(/\.00$/, '')
            : ''
    );
    const discountTimesTenNumber = parseFloat(discountText.replace(/[^\d.-]/g, ''));
    const discountTimesTen = Number.isFinite(discountTimesTenNumber)
        ? (discountTimesTenNumber * 10).toFixed(2).replace(/\.00$/, '')
        : '';
    const monthlyInterestNumeric = parseFloat(String(monthlyInterestRate).replace(/[^\d.-]/g, ''));
    const monthlyInterestText = Number.isFinite(monthlyInterestNumeric)
        ? monthlyInterestNumeric.toFixed(2).replace(/\.00$/, '')
        : String(monthlyInterestRate || '');
    const adjustedMonthlyInterest = Number.isFinite(monthlyInterestNumeric)
        ? (monthlyInterestNumeric - 0.2).toFixed(2).replace(/\.00$/, '')
        : '';
    const yearlyInterest = Number.isFinite(monthlyInterestNumeric)
        ? (monthlyInterestNumeric * 12).toFixed(2).replace(/\.00$/, '')
        : '';
    const collateralTypeText = collateralType || '';
    const attributionText = attribution || '';
    const repaymentTableRaw = createRepaymentTableXml(calculation);

    return {
        borrower_name: borrowerName,
        borrower_card: borrowerCard,
        borrower_phone: borrowerPhone,
        borrowerPhone: borrowerPhone,
        borrower_phone: borrowerPhone,
        collateral,
        collateral_type: collateralTypeText,
        collateralType: collateralTypeText,
        attribution: attributionText,
        borrower_area: borrowerArea || collateral,
        borrowerArea: borrowerArea || collateral,
        collateral_area: collateralArea,
        collateralArea: collateralArea,
        pawn_ticket: pawnTicket,
        pawnTicket: pawnTicket,
        collateral1,
        borrower_total: borrowerTotal,
        collateral_discount: discountText,
        collateralDiscount: discountText,
        collateral_discount1: discountTimesTen,
        collateralDiscount1: discountTimesTen,
        monthly_interest_rate: monthlyInterestText,
        monthly_interest_rate1: adjustedMonthlyInterest,
        year_interest_rate: yearlyInterest,
        borrower_year: String(start.getFullYear()),
        borrower_month: String(start.getMonth() + 1),
        borrower_day: String(start.getDate()),
        borrower_money: roundedAmount.toFixed(2),
        borrower_daxie_money: numberToChinese(roundedAmount),
        borrower_month_ratio: (calculation.monthlyRate * 100).toFixed(2),
        borrower_return_year: String(due.getFullYear()),
        borrower_return_month: String(due.getMonth() + 1),
        borrower_return_day: String(due.getDate()),
        borrower_return_year1: String(halfYearDue.getFullYear()),
        borrower_return_month1: String(halfYearDue.getMonth() + 1),
        borrower_return_day1: String(halfYearDue.getDate()),
        repayment_table: repaymentTableRaw ? { __raw: repaymentTableRaw } : '',
    };
}

function createRepaymentTableXml(calculation) {
    if (!calculation || !Array.isArray(calculation.schedule) || calculation.schedule.length === 0) {
        return '';
    }

    const fontTag = '<w:rFonts w:hint="eastAsia" w:ascii="宋体" w:hAnsi="宋体" w:eastAsia="宋体"/>';
    const buildRun = (text, { underline = false, preserve = false } = {}) => {
        const attrs = underline
            ? `<w:rPr>${fontTag}<w:u w:val="single"/></w:rPr>`
            : `<w:rPr>${fontTag}</w:rPr>`;
        const spaceAttr = preserve ? ' xml:space="preserve"' : '';
        return `<w:r>${attrs}<w:t${spaceAttr}>${escapeXml(text)}</w:t></w:r>`;
    };

    const paragraphs = calculation.schedule.map((entry, index) => {
        const sequence = index + 1;
        const dateText = formatDate(entry.date instanceof Date ? entry.date : new Date(entry.date));
        const monthlyPayment = Number.isFinite(entry.monthlyPayment) ? entry.monthlyPayment : 0;
        const principal = Number.isFinite(entry.principal) ? entry.principal : 0;
        const interest = Number.isFinite(entry.interest) ? entry.interest : 0;
        const remainingPrincipal = Number.isFinite(entry.remainingPrincipal) ? entry.remainingPrincipal : 0;

        const runs = [
            buildRun(`第${sequence}期： `, { preserve: true }),
            buildRun(dateText, { underline: true }),
            buildRun(' 前还人民币 ', { preserve: true }),
            buildRun(monthlyPayment.toFixed(2), { underline: true }),
            buildRun(' 元（本金 ', { preserve: true }),
            buildRun(principal.toFixed(2), { underline: true }),
            buildRun('；利息 ', { preserve: true }),
            buildRun(interest.toFixed(2), { underline: true }),
            buildRun('；剩余本金 ', { preserve: true }),
            buildRun(Math.max(remainingPrincipal, 0).toFixed(2), { underline: true }),
            buildRun('）。'),
        ];

        return `<w:p><w:pPr><w:jc w:val="left"/><w:spacing w:after="300"/></w:pPr>${runs.join('')}</w:p>`;
    });

    return paragraphs.join('');
}

async function loadTemplateBuffer(config, cache) {
    if (cache.has(config.templatePath)) {
        return cache.get(config.templatePath);
    }

    const absolutePath = new URL(config.templatePath, document.baseURI).href;

    try {
        const response = await fetch(absolutePath, { cache: 'no-store' });
        if (!response.ok && response.status !== 0) {
            throw new Error(`无法加载模板：${config.templatePath}`);
        }
        const buffer = await response.arrayBuffer();
        cache.set(config.templatePath, buffer);
        return buffer;
    } catch (error) {
        const fallbackBuffer = await loadTemplateViaXHR(absolutePath);
        cache.set(config.templatePath, fallbackBuffer);
        return fallbackBuffer;
    }
}

async function fillDocxTemplate(templateBuffer, placeholders) {
    const zip = await window.JSZip.loadAsync(templateBuffer.slice(0));
    const xmlPath = 'word/document.xml';
    const xmlContent = await zip.file(xmlPath).async('string');

    const normalizedXml = normalizeSplitPlaceholders(xmlContent, Object.keys(placeholders));
    const filledXml = replacePlaceholders(normalizedXml, placeholders);

    if (/\{\{\s*[^}]+\s*\}\}/.test(filledXml)) {
        const leftovers = Array.from(new Set(filledXml
            .match(/\{\{\s*([^}]+)\s*\}\}/g)
            ?.map((raw) => raw.replace(/\{\{|\}\}/g, '').trim()) || []));
        if (leftovers.length > 0) {
            console.warn('未替换占位符：', leftovers);
        }
    }

    zip.file(xmlPath, filledXml);
    return zip.generateAsync({ type: 'blob' });
}

function normalizeSplitPlaceholders(xml, keys) {
    let normalized = keys.reduce((content, key) => {
        const pattern = new RegExp(
            `<w:t([^>]*)>\\s*{{\\s*</w:t>\\s*</w:r>\\s*<w:r[^>]*>(?:<w:rPr>[\\s\\S]*?</w:rPr>)?<w:t[^>]*>\\s*${escapeRegExp(key)}\\s*</w:t></w:r>\\s*<w:r[^>]*>(?:<w:rPr>[\\s\\S]*?</w:rPr>)?<w:t([^>]*)>\\s*}}\\s*</w:t>`,
            'gi'
        );
        let updated = content.replace(pattern, (_match, firstAttrs = '', lastAttrs = '') => {
            const attrs = firstAttrs && firstAttrs.trim() ? firstAttrs : lastAttrs;
            return `<w:t${attrs}>{{${key}}}</w:t>`;
        });
        if (key.includes('_')) {
            const parts = key.split('_');
            const prefix = escapeRegExp(parts.slice(0, -1).join('_'));
            const suffix = escapeRegExp(parts[parts.length - 1]);
            const compositePattern = new RegExp(
                `<w:t([^>]*)>\\s*{{\\s*${prefix}_</w:t>\\s*</w:r>\\s*<w:r[^>]*>(?:<w:rPr>[\\s\\S]*?</w:rPr>)?<w:t[^>]*>\\s*${suffix}\\s*</w:t></w:r>\\s*<w:r[^>]*>(?:<w:rPr>[\\s\\S]*?</w:rPr>)?<w:t([^>]*)>\\s*}}\\s*</w:t>`,
                'gi'
            );
            updated = updated.replace(compositePattern, (_match, firstAttrs = '', lastAttrs = '') => {
                const attrs = firstAttrs && firstAttrs.trim() ? firstAttrs : lastAttrs;
                return `<w:t${attrs}>{{${key}}}</w:t>`;
            });
        }
        return updated;
    }, xml);

    const genericPattern = /{{[\s\S]*?}}/g;
    normalized = normalized.replace(genericPattern, (segment) => {
        const plain = segment
            .replace(/<[^>]+>/g, '')
            .replace(/[{}]/g, '')
            .replace(/\s+/g, '');
        return keys.includes(plain) ? `{{${plain}}}` : segment;
    });

    return normalized;
}

function replacePlaceholders(xml, placeholders) {
    return Object.entries(placeholders).reduce((content, [key, value]) => {
        const pattern = new RegExp(`{{\\s*${escapeRegExp(key)}\\s*}}`, 'g');
        if (value && typeof value === 'object' && Object.prototype.hasOwnProperty.call(value, '__raw')) {
            const paragraphPattern = new RegExp(
                `<w:p[^>]*>\\s*(?:<[^>]+>\\s*)*{{\\s*${escapeRegExp(key)}\\s*}}\\s*(?:<[^>]+>\\s*)*</w:p>`,
                'g'
            );
            const replacedParagraphs = content.replace(paragraphPattern, () => value.__raw);
            if (replacedParagraphs !== content) {
                return replacedParagraphs;
            }
            const runPattern = new RegExp(
                `<w:r[^>]*>(?:<w:rPr>[\\s\\S]*?</w:rPr>)?<w:t[^>]*>\\s*{{\\s*${escapeRegExp(key)}\\s*}}\\s*</w:t>\\s*</w:r>`,
                'g'
            );
            const replacedRuns = content.replace(runPattern, () => value.__raw);
            if (replacedRuns !== content) {
                return replacedRuns;
            }
            return content.replace(pattern, value.__raw);
        }
        return content.replace(pattern, escapeXml(value));
    }, xml);
}

function escapeRegExp(text) {
    return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeXml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function appendCopySuffix(fileName, index) {
    const dotIndex = fileName.lastIndexOf('.');
    if (dotIndex === -1) {
        return `${fileName}-第${index}份`;
    }

    const baseName = fileName.slice(0, dotIndex);
    const extension = fileName.slice(dotIndex);
    return `${baseName}-第${index}份${extension}`;
}

function sanitizeFileSystemName(input) {
    return String(input || '')
        .replace(/[\\/:*?"<>|]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function triggerFileDownload(blob, fileName) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => URL.revokeObjectURL(url), 0);
}

function computeDueDate(startDate, loanMonths) {
    const due = new Date(startDate.getTime());
    due.setMonth(due.getMonth() + loanMonths);
    due.setDate(due.getDate() - 1);
    return due;
}
function loadTemplateViaXHR(path) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', path, true);
        xhr.responseType = 'arraybuffer';
        xhr.onload = () => {
            if (xhr.status === 200 || xhr.status === 0) {
                resolve(xhr.response);
            } else {
                reject(new Error(`无法加载模板：${path}`));
            }
        };
        xhr.onerror = () => reject(new Error(`无法加载模板：${path}`));
        xhr.send();
    });
}
global.AppContract = {
    initContractDownload,
};
})(window);
