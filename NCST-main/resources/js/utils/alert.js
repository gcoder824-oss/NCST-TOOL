import Swal from 'sweetalert2';

// Set up NCST styling defaults for sweetalert
const customSwal = Swal.mixin({
    background: '#0a192f', // ncst-navy (or close to it)
    color: '#fff',
    confirmButtonColor: '#ffc107', // ncst-gold
    cancelButtonColor: '#ef4444', // red-500
    customClass: {
        popup: 'border border-ncst-gold/20 rounded-2xl shadow-2xl',
        title: 'text-xl font-bold text-white',
        htmlContainer: 'text-sm text-white/70',
        confirmButton: 'px-4 py-2 rounded-lg font-semibold text-ncst-navy',
        cancelButton: 'px-4 py-2 rounded-lg font-semibold text-white'
    }
});

export const useAlert = () => {
    return {
        success: (title, text = '') => {
            return customSwal.fire({
                title,
                text,
                icon: 'success'
            });
        },
        error: (title, text = '') => {
            return customSwal.fire({
                title,
                text,
                icon: 'error'
            });
        },
        warning: (title, text = '') => {
            return customSwal.fire({
                title,
                text,
                icon: 'warning'
            });
        },
        info: (title, text = '') => {
            return customSwal.fire({
                title,
                text,
                icon: 'info'
            });
        },
        confirm: (title, text = '', confirmButtonText = 'Yes', cancelButtonText = 'Cancel') => {
            return customSwal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText
            });
        },
        toast: (title, icon = 'success') => {
            const Toast = Swal.mixin({
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: '#0a192f',
                color: '#fff',
                customClass: {
                    popup: 'border border-ncst-gold/20 rounded-xl'
                },
                didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                }
            });
            Toast.fire({
                icon,
                title
            });
        }
    }
}
