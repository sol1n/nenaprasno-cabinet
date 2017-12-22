
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


function handleSocial(networkName, userId) {
    var url = '/loginBySocial';
    var _token = $('input[name="_token"]').val();
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            userId: userId,
            networkName: networkName,
            _token: _token
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


function loginFb(e){
    e.preventDefault();
    FB.getLoginStatus(function(response) {
        console.log(response);
        if (response.authResponse) {
            console.log('Welcome!  Fetching your information.... ');
            handleSocial(SOCIAL_FB, response.authResponse.userID);
        } else {
            console.log('Юзер был не залогинен в самом ФБ, запускаем окно логинизирования');
            FB.login(function(response){
                if (response.authResponse) {
                    console.log('Welcome!  Fetching your information.... ');
                    console.log(response);
                } else {
                    console.log('Походу пользователь передумал логиниться через ФБ');
                }
            });
        }
    }, {
        scope: 'email,id'
    });
}

function loginVk(e) {
    e.preventDefault();
    VK.Auth.login(function(res){
        if (res.status == "connected" && res.hasOwnProperty('session')) {
            handleSocial(SOCIAL_VK, res.session.user.id);
        }
    }, 4194304 );
}