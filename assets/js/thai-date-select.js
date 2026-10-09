// Reads/writes the วัน/เดือน/ปี (พ.ศ.) <select> triple from
// includes/functions.php:renderThaiDateSelect(), as one ISO (Gregorian)
// date string - used wherever a date-range filter needs a reliable
// วัน-เดือน-ปี order instead of the browser's native <input type="date">.

// Returns 'YYYY-MM-DD', or '' if day/month/year aren't all chosen (treated
// as "no filter" by the pages that use this).
function thaiDateSelectGet(prefix) {
    const day = $(`#${prefix}_day`).val();
    const month = $(`#${prefix}_month`).val();
    const yearBE = $(`#${prefix}_year`).val();
    if (!day || !month || !yearBE) {
        return '';
    }
    const yearAD = parseInt(yearBE, 10) - 543;
    return `${yearAD}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// Sets the three selects from an ISO (Gregorian) date string, or clears
// them if isoDate is empty/null.
function thaiDateSelectSet(prefix, isoDate) {
    if (!isoDate) {
        $(`#${prefix}_day, #${prefix}_month, #${prefix}_year`).val('');
        return;
    }
    const [year, month, day] = isoDate.split('-').map((n) => parseInt(n, 10));
    $(`#${prefix}_day`).val(day);
    $(`#${prefix}_month`).val(month);
    $(`#${prefix}_year`).val(year + 543);
}
