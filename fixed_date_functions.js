// Fixed JavaScript Date/Time Conversion Functions for OpenEMR Inventory Module

function DateToYYYYMMDD_js(value) {
    if (!value || value.trim() === '') {
        return '';
    }
    
    try {
        var cleanValue = value.replace(/\//g, '-');
        var parts = cleanValue.split('-');
        
        if (parts.length !== 3) {
            console.error('Invalid date format:', value);
            return value; // Return original if can't parse
        }
        
        var date_display_format = "1"; // This should come from system settings
        
        if (date_display_format == 1) {
            // mm/dd/yyyy format - convert to yyyy-mm-dd
            var year = parts[2];
            var month = parts[0].padStart(2, '0');
            var day = parts[1].padStart(2, '0');
            return year + '-' + month + '-' + day;
        } else if (date_display_format == 2) {
            // dd/mm/yyyy format - convert to yyyy-mm-dd
            var year = parts[2];
            var month = parts[1].padStart(2, '0');
            var day = parts[0].padStart(2, '0');
            return year + '-' + month + '-' + day;
        }
        
        return value;
    } catch (error) {
        console.error('Error in DateToYYYYMMDD_js:', error, 'Input:', value);
        return value; // Return original value if error
    }
}

function TimeToHHMMSS_js(value) {
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
                console.error('Invalid time format:', value);
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
        
        return value;
    } catch (error) {
        console.error('Error in TimeToHHMMSS_js:', error, 'Input:', value);
        return value; // Return original value if error
    }
}

function DateToYYYYMMDDHHMMSS_js(value) {
    if (!value || typeof value === 'undefined' || value.trim() === '') {
        return undefined;
    }
    
    try {
        var parts = value.trim().split(' ');
        
        if (parts.length < 2) {
            console.error('Invalid datetime format - missing time part:', value);
            return value;
        }
        
        var datePart = DateToYYYYMMDD_js(parts[0]);
        var timePart = TimeToHHMMSS_js(parts[1]);
        
        if (!datePart || !timePart) {
            console.error('Failed to parse date or time parts:', value);
            return value;
        }
        
        return datePart + ' ' + timePart;
    } catch (error) {
        console.error('Error in DateToYYYYMMDDHHMMSS_js:', error, 'Input:', value);
        return value; // Return original value if error
    }
}

// Additional helper function for date formatting used in OpenEMR
function oeFormatShortDate_js(dateStr) {
    if (!dateStr || dateStr === '0000-00-00' || dateStr.trim() === '') {
        return '';
    }
    
    try {
        // If already in YYYY-MM-DD format, return as is
        if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
            return dateStr;
        }
        
        // Otherwise, try to parse and format
        var date = new Date(dateStr);
        if (isNaN(date.getTime())) {
            console.error('Invalid date:', dateStr);
            return '';
        }
        
        var year = date.getFullYear();
        var month = (date.getMonth() + 1).toString().padStart(2, '0');
        var day = date.getDate().toString().padStart(2, '0');
        
        return year + '-' + month + '-' + day;
    } catch (error) {
        console.error('Error in oeFormatShortDate_js:', error, 'Input:', dateStr);
        return '';
    }
}

console.log('Fixed date/time functions loaded successfully');
