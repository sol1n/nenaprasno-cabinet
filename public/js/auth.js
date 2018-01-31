
$(function() {
    VK.init({apiId: APP_VK});
});

window.fbAsyncInit = function() {
    FB.init({
        appId      : APP_FB,
        cookie     : true,
        xfbml      : true,
        version    : 'v2.11'
    });

    FB.AppEvents.logPageView();

};

(function(d, s, id){
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) {return;}
    js = d.createElement(s); js.id = id;
    js.src = "https://connect.facebook.net/ru_ru/sdk.js";
    fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));


function handleSocial(networkName, userId, data = {}) {
    var url = '/loginBySocial';
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            userId: userId,
            data: data,
            networkName: networkName
        }
    })
        .done(function (response) {
            console.log(response);
            if (response.type == 'success') {
                window.location.href = response.data;
            }
            else {
                console.log(response.msg);
            }
        })
        .fail(function(jxhr, status){
            console.log(status);
        });
}

const SOCIAL_FB = 'fb';
const SOCIAL_VK = 'vk';


function getFbData(userId) {
    FB.api(
        '/' + userId + '?fields=id,name,first_name,last_name,birthday,gender, email',
        'GET',
        {},
        function(response) {
            var data = {};
            if (response.hasOwnProperty('name') && response.name) {
                data['fio'] = response.name;
            }
            if (response.hasOwnProperty('birthday') && response.birthday) {
                data['birthday'] = response.birthday;
            }
            if (response.hasOwnProperty('gender') && response.gender) {
                data['gender'] = (response.gender == 'male' ? 0 : 1);
            }
            if (response.hasOwnProperty('email') && response.email) {
                data['email'] = response.email;
            }
            console.log(data);
            handleSocial(SOCIAL_FB, userId, data);
        }
    );
}

function loginFb(e){
    e.preventDefault();
    FB.getLoginStatus(function(response) {
        console.log(response);
        if (response.authResponse) {
            getFbData(response.authResponse.userID);
        } else {
            FB.login(function(response){
                console.log(response);
                if (response.authResponse) {
                    getFbData(response.authResponse.userID);
                }
            },{scope:'email, public_profile'});
        }
    }, {
        scope: 'email, public_profile'
    });
}

function loginVk(e) {
    e.preventDefault();
    VK.Auth.login(function(res){
        console.log(res);
        if (res.status == "connected" && res.hasOwnProperty('session')) {
            VK.api('users.get', {'user_ids': res.session.user.id, 'fields': 'id,first_name,last_name,sex,bdate,email'}, function(response) {
                if (response.hasOwnProperty('response') && response.response.length > 0) {
                    response = response.response[0];
                    var data = {};
                    var first_name = '';
                    if (response.hasOwnProperty('first_name') && response.first_name) {
                        first_name = response.first_name;
                    }
                    var last_name = '';
                    if (response.hasOwnProperty('last_name') && response.last_name) {
                        last_name = response.last_name;
                    }
                    if (first_name || last_name) {
                        data['fio'] = [first_name, last_name].join(' ');
                    }
                    if (response.hasOwnProperty('sex') && response.sex) {
                        data['gender'] = response.sex == 2 ? 0 : 1;
                    }
                    if (response.hasOwnProperty('bdate') && response.bdate) {
                        data['birthday'] = moment(response.bdate, 'DD.M.YYYY').format('DD.MM.YYYY');
                    }
                    handleSocial(SOCIAL_VK, res.session.user.id, data);
                }
            });
        }
    }, 4194304 );
}

$(function() {
    $('#confirm').keyup(function(){
        var password = $('#password').val();
        var confirm = $('#confirm').val();
        var element = document.getElementById('confirm');
        if (confirm != password){
            element.setCustomValidity('Пароль и подтверждения пароля должны совпадать');
        }
        else{
            element.setCustomValidity('');
        }
    });
});