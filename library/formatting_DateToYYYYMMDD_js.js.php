<?php
// Set proper content type for JavaScript
header('Content-Type: application/javascript');

// Use a default date display format if globals aren't available
$date_display_format = 1; // mm/dd/yyyy format

// Set proper MIME type for JavaScript
header('Content-Type: application/javascript; charset=utf-8');

?>

function DateToYYYYMMDD_js(value){
    if (!value || value.trim() === '') {
        return '';
    }
    
    try {
        var cleanValue = value.replace(/\//g,'-');
        var parts = cleanValue.split('-');
        var date_display_format = <?php echo $date_display_format; ?>;

        if (parts.length !== 3) {
            console.error('DateToYYYYMMDD_js: Invalid date format:', value);
            return value; // Return original if can't parse
        }

        if (date_display_format == 1) {      // mm/dd/yyyy, note year is added below
            var year = parts[2];
            var month = parts[0].padStart(2, '0');
            var day = parts[1].padStart(2, '0');
            return year + '-' + month + '-' + day;
        }
        else if (date_display_format == 2) { // dd/mm/yyyy, note year is added below
            var year = parts[2];
            var month = parts[1].padStart(2, '0');
            var day = parts[0].padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        return value;
    } catch (error) {
        console.error('DateToYYYYMMDD_js error:', error, 'Input:', value);
        return value; // Return original value if error
    }
}

function TimeToHHMMSS_js(value){
    if (!value || value.trim() === '') {
        return '';
    }
    
    try {
        var timeValue = value.trim().toUpperCase();
        var isPM = timeValue.indexOf('PM') > -1;
        var isAM = timeValue.indexOf('AM') > -1;
        
        // Remove AM/PM indicators
        var cleanTime = timeValue.replace(/(AM|PM)/g, '').trim();
        
        if (isPM || isAM) {
            // Handle 12-hour format
            var date = new Date("1970-01-01 " + cleanTime);
            
            if (isNaN(date.getTime())) {
                console.error('TimeToHHMMSS_js: Invalid time format:', value);
                return value;
            }
            
            if (isPM && date.getHours() < 12) {
                date.setHours(date.getHours() + 12);
            } else if (isAM && date.getHours() === 12) {
                date.setHours(0);
            }
            
            // Format as HH:MM:SS
            var hours = date.getHours().toString().padStart(2, '0');
            var minutes = date.getMinutes().toString().padStart(2, '0');
            var seconds = date.getSeconds().toString().padStart(2, '0');
            
            return hours + ':' + minutes + ':' + seconds;
        } else {
            // Already in 24-hour format, just ensure proper formatting
            var timeParts = cleanTime.split(':');
            if (timeParts.length >= 2) {
                var hours = timeParts[0].padStart(2, '0');
                var minutes = timeParts[1].padStart(2, '0');
                var seconds = (timeParts[2] || '00').padStart(2, '0');
                return hours + ':' + minutes + ':' + seconds;
            }
        }
        
        return value.trim();
    } catch (error) {
        console.error('TimeToHHMMSS_js error:', error, 'Input:', value);
        return value.trim(); // Return original value if error
    }
}

function DateToYYYYMMDDHHMMSS_js(value){
    if (!value || typeof value === 'undefined' || value.trim() === '') {
        return undefined;
    }
    
    try {
        var parts = value.trim().split(' ');
        
        if (parts.length < 2) {
            console.error('DateToYYYYMMDDHHMMSS_js: Invalid datetime format - missing time part:', value);
            return value;
        }
        
        var datePart = DateToYYYYMMDD_js(parts[0]);
        var timePart = TimeToHHMMSS_js(parts[1]);
        
        if (!datePart || !timePart) {
            console.error('DateToYYYYMMDDHHMMSS_js: Failed to parse date or time parts:', value);
            return value;
        }
        
        return datePart + ' ' + timePart;
    } catch (error) {
        console.error('DateToYYYYMMDDHHMMSS_js error:', error, 'Input:', value);
        return value; // Return original value if error
    }
}
