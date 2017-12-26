<template>
    <div>
        <div class="info-msg">
            Для восстановления вашего пароля введите код восстановления и имя пользователя, которые были высланы на вашу почту
        </div>
        <div class="cabinet-profile-control" v-if="errors.length > 0">
            <div v-for="error in errors" class="cabinet-error">
                {{ error }}
            </div>
        </div>

        <div class="cabinet-profile-control">
            <label class="cabinet-profile-label">Имя пользователя</label>
            <input autofocus type="text" class="form-input" placeholder="Введите имя пользователя" v-model="username">
        </div>

        <div class="cabinet-profile-control">
            <label class="cabinet-profile-label">Код восстановления</label>
            <input autofocus type="text" class="form-input" placeholder="Введите код восстановления" v-model="recoveryCode">
        </div>

        <div class="cabinet-profile-control">
            <label class="cabinet-profile-label">Новый пароль</label>
            <input type="password" class="form-input" placeholder="Введите пароль" v-model="password">
        </div>

        <div class="cabinet-profile-control">
            <label class="cabinet-profile-label">Повторите новый пароль</label>
            <input type="password" class="form-input" placeholder="Повторите пароль" v-model="passwordSecond">
        </div>

        <div class="cabinet-profile-submit">
            <button @click="send()" type="button" class="button button-blue button-round">
                Установить новый пароль пароль
            </button>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'restore-form',
        data() {
            return {
                recoveryCode: '',
                password: '',
                passwordSecond: '',
                username: '',
                errors: []
            }
        },
        methods: {
            send() {
                this.errors = [];
                if (this.password == this.passwordSecond) {
                    if (this.recoveryCode && this.password && this.username) {
                        var url = '/restore-pswd';
                        axios.post(url, {
                            recoveryCode: this.recoveryCode,
                            password: this.password,
                            username: this.username
                        })
                            .then((response) => {
                                console.log(response);
                                if (response.data.type === 'success') {
                                    this.$parent.isSuccess = true;
                                    this.$parent.complete();
                                }
                                else{
                                    response.data.msg.forEach((error) => {
                                        this.errors.push(error);
                                    })
                                }
                            })
                            .catch((error) => {
                                console.log(error);
                                if (error && error.hasOwnProperty('response') && error.response.hasOwnProperty('status') && error.response.status == 422) {
                                    for(var index in error.response.data) {
                                        if (error.response.data.hasOwnProperty(index)) {
                                            error.response.data[index].forEach((error) => {
                                                this.errors.push(error);
                                            })
                                        }
                                    }
                                }
                                else {
                                    alert('Произошла ошибка во время выполнения запроса');
                                }
                            })
                    }
                    else {
                        this.errors.push('Для смены пароля введите имя пользователся, код восстановления и новый пароль');
                    }
                }
                else {
                    this.errors.push('Пароли не совпадают');
                }
            }
        }
    }
</script>

<style scoped>
    .info-msg {
        margin-bottom: 20px;
    }
</style>