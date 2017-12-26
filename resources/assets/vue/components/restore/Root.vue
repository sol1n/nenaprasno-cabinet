<template>
    <div class="cabinet-profile">
        <div class="cabinet-profile-card m-t-lg m-b-lg" :class="{'restore-success': isSuccess}" style="max-width: 570px; margin-left: auto; margin-right: auto;">
            <div class="cabinet-profile-title" v-if="!isSuccess">
                Восстановление пароля
            </div>
            <div class="cabinet-profile-title" v-else>
                Восстановление пароля прошло успешно
            </div>
            <recovery-form v-if="showRecovery"></recovery-form>
            <restore-form v-else-if="showRestore"></restore-form>
            <success v-else="showSuccess"></success>
        </div>
    </div>
</template>

<script>
    import RecoveryForm from './RecoveryForm.vue';
    import RestoreForm from './RestoreForm.vue';
    import Success from './Success.vue';

    export default {
        name: 'restore',
        computed: {
          showRecovery() {
              return this.isRecovery && !this.isSuccess;
          },
          showRestore() {
              return !this.isRecovery && !this.isSuccess;
          },
          showSuccess() {
              return this.isSuccess;
          }
        },
        components: {
            RecoveryForm,
            RestoreForm,
            Success
        },
        data() {
            return {
                isRecovery: true,
                email: '',
                username: '',
                isSuccess: false
            }
        },
        methods: {
            complete() {
                setTimeout(function() {
                    window.location.href = '/login';
                }, 3000)
            }
        },
        mounted() {
            this.isRecovery = true;
            this.isSuccess = false;
        }
    }
</script>

<style scoped>
    .restore-success {
        /*background-color: #fff;*/
        text-align: center;
    }
</style>
