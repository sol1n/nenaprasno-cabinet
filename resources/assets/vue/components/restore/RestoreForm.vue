<template>
    <div>
        <div class="info-msg">
            Для восстановления вашего пароля введите код восстановления, который был выслан на вашу почту
        </div>
        <div class="cabinet-profile-control" v-if="errors.length > 0">
            <div v-for="error in errors" class="cabinet-error">
                {{ error }}
            </div>
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
            <input type="password" class="form-input" placeholder="Повторите пароль" v-model="_password">
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
                _password: '',
                errors: []
            }
        },
        methods: {
            send() {
                this.errors = [];
                if (this.password == this._password) {
                    if (this.recoveryCode && this.password) {

                    }
                    else {
                        this.errors.push('Для смены пароля введите код восстановления и новый пароль');
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