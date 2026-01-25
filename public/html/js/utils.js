(function (global) {
    function numberToChinese(n) {
        const fraction = ['角', '分'];
        const digit = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
        const unit = [['元', '万', '亿'], ['', '拾', '佰', '仟']];
        let head = n < 0 ? '负' : '';
        let amount = Math.abs(n);

        let result = '';

        for (let i = 0; i < fraction.length; i += 1) {
            result += (digit[Math.floor(amount * 10 * Math.pow(10, i)) % 10] + fraction[i]).replace(/零./, '');
        }

        result = result || '整';
        amount = Math.floor(amount);

        for (let i = 0; i < unit[0].length && amount > 0; i += 1) {
            let partial = '';
            for (let j = 0; j < unit[1].length && amount > 0; j += 1) {
                partial = digit[amount % 10] + unit[1][j] + partial;
                amount = Math.floor(amount / 10);
            }
            result = partial.replace(/(零.)*零$/, '').replace(/^$/, '零') + unit[0][i] + result;
        }

        let finalText = result
            .replace(/(零.)*零元/, '元')
            .replace(/(零.)+/g, '零')
            .replace(/^整$/, '零');
        const output = head + finalText;
        return output
            .replace(/元整$/, '')
            .replace(/元$/, '')
            .replace(/整$/, '');
    }

    function formatDate(date) {
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        return `${year}年${month}月${day}日`;
    }

    global.AppUtils = {
        numberToChinese,
        formatDate,
    };
})(window);
