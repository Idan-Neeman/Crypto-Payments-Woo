runInterval();

function runInterval() {
    if (document.getElementById("check-time") == null) return;
    var timeleft = document.getElementById("check-time").innerHTML;
    var checkingTimer = setInterval(function () {
        if (timeleft <= 0) {
            check_balance();
            clearInterval(checkingTimer);
        }
        else {
            document.getElementById("check-time").innerHTML = timeleft;
            timeleft -= 1;
        }
    }, 1000);
}

function check_balance() {
    if (document.getElementById("check-time-msg") == null) return;
    document.getElementById("check-time-msg").innerHTML = "<small id='check-time-msg'>" + localize_strings.checking_balance + "</small>";
    loadXMLDoc(ajaxurl + "?action=balance_check_action");
}

function loadXMLDoc(url) {
    var xmlhttp = new XMLHttpRequest();

    xmlhttp.onreadystatechange = function () {
        if (xmlhttp.readyState == XMLHttpRequest.DONE) {
            if (xmlhttp.status == 200) {
                update_status(xmlhttp.responseText);
            }
            else {
                var output = {};
                output.status = localize_strings.error;
                update_status(output);
            }
        }
    };

    xmlhttp.open("POST", url, true);
    xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    var parse_url = (new URL(document.location)).searchParams;
    var show_order = parse_url.get("show_order");
    var params = 'show_order=' + show_order;
    xmlhttp.send(params);
}

function update_status(data) {
    if (data == "Access Restricted!" || data.result == localize_strings.error) {
        document.getElementById("check-time-msg").style.display = "none";
        document.getElementById("loader").style.display = "none";
        document.getElementById("status-msg").innerHTML = localize_strings.error;
        document.getElementById("status-msg").style.color = "red";
    }
    else if (JSON.parse(data).status == 'pending') {
        document.getElementById("check-time-msg").innerHTML = "<small id='check-time-msg'>" + localize_strings.checking_balance_timer + "</small>";
        runInterval();
    }
    else if (JSON.parse(data).status == 'completed') {
        document.getElementById("status-msg").innerHTML = localize_strings.payment_arrived;
        document.getElementById("check-time-msg").style.display = "none";
        document.getElementById("loader").style.display = "none";
        setTimeout(function () {
            location.reload()
        }, 3000);
    }
}