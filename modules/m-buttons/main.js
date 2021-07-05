    // loading styles
var link = document.createElement('link');
link.href = "/_buran/modules/m-buttons/style.css";
link.rel = 'stylesheet';
link.type = 'text/css';
link.media = 'all';
document.head.appendChild(link);

    // main form
var elem = document.createElement('div');
elem.id = "m-buttons-main";
elem.onclick = function () {
    if (this.classList.contains('opened')) {
        document.getElementById('m-buttons-all').style.display = "none";
        document.getElementById('m-buttons-main').classList.remove("opened");
    } else {
        document.getElementById('m-buttons-all').style.display = "block";
        document.getElementById('m-buttons-main').classList.add("opened");
    }
};
document.body.appendChild(elem);

    // popup form
var elem2 = document.createElement('div');
elem2.innerHTML = '<a class="m-buttons-whatsapp m-buttons-btn" href="https://wa.me/79289602779" target="_blank"><i style=""></i></a>';
elem2.innerHTML += '<div id="chatra-button" class="chatra m-buttons-btn" onclick="Chatra(\'show\');Chatra(\'openChat\', true);"></div>';
elem2.innerHTML += '<div class="m-buttons-callback m-buttons-btn" onclick="showform()"></div>';
elem2.id = "m-buttons-all";
elem2.onclick = function () {
    // document.getElementById('m-buttons-all').style.display = "none";
};

document.body.appendChild(elem2);



function showform(){
    var formwrap = document.getElementsByClassName('formochka-modal');
    
    if (formwrap.length > 0) {
        document.getElementsByClassName('formochka-modal')[0].style.display = 'block';
    } else {
        const data = { username: 'example' };

        fetch('/_buran/modules/m-buttons/_action.php', {
            method: 'post',
            headers: {
                "Content-type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: 'showform'       
        }).then((response) => {
            return response.text();
        }).then((data) => {
            var formochka = document.createElement('div');
            formochka.classList.add("formochka-modal");
            formochka.innerHTML = data;
            document.body.appendChild(formochka);

            document.getElementById('formochka-close').onclick = function () {
                document.getElementsByClassName('formochka-modal')[0].style.display = 'none';
            }
            document.getElementById('formochka-submit').onclick = function () {
                var input1 = document.getElementById("input1").value;
                var input2 = document.getElementById("input2").value;

                fetch('/_buran/modules/m-buttons/_action.php', {
                    method: 'post',
                    headers: {
                        "Content-type": "application/x-www-form-urlencoded; charset=UTF-8"
                    },
                    body: 'submitform&input1=' + input1 + '&input2=' + input2
                }).then((response) => {
                    return response.text();
                }).then((data) => {
                    document.getElementsByClassName('formochka-container')[0].innerHTML = data;
                        
                }); 


            }
            

        }); 

    }

   

}

