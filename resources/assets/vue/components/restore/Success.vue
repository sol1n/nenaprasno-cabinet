<template>
    <div>
        <img src="/images/icon-restore-success.svg" alt="success">
        <div class="success-msg">
            Вы будуте перенаправлены на страницу входа
        </div>
        <div class="success-login-link">
            <a href="/login">Вход</a>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'success',
        data() {
            return {}
        },
        mounted() {

        }
    }
</script>

<style scoped>
    .success-msg {
        margin-top: 30px;
        font-size: 14px;
        color: #666;
    }

    .success-login-link {
        margin: 10px 0;
        font-size: 16px;
    }
</style>
