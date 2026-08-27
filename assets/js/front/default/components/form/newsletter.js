/**
 * Newsletter form
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */

import Modal from '../../../bootstrap/dist/modal';
import {onSubmit} from "../../../../vendor/components/recaptcha";

export default function () {

    /** Delai laisse a la requete du widget avant de quitter la page. */
    const WIDGET_DELAY = 1500;

    /** To display Modal */
    let showModal = function (modalEl, hide = false) {
        let cloneModal = modalEl.cloneNode(true);
        let modal = new Modal(cloneModal, {
            keyboard: false
        })
        modal.show();
        if (hide) {
            setTimeout(function () {
                modal.hide()
            }, 4500)
        }
    }

    /** Reset inputs */
    let resetInputs = function () {
        document.querySelectorAll('.newsletter-form-email').forEach(function (input) {
            input.setAttribute('value', '');
        });
        document.querySelectorAll('.external-input-email').forEach(function (input) {
            input.setAttribute('value', '');
        });
    }

    resetInputs();

    /**
     * Prenom / nom : reveles des que l'e-mail est saisi.
     * Reveles aussi au chargement si le champ est deja rempli ou si le serveur a renvoye une erreur.
     */
    let identityFields = function () {
        document.querySelectorAll('.newsletter-form').forEach(function (form) {
            let block = form.querySelector('.newsletter-identity');
            let email = form.querySelector('.newsletter-form-email');
            if (!block || !email) {
                return;
            }
            let toggle = function () {
                let filled = email.value.trim().length > 0;
                let identityFilled = [...block.querySelectorAll('input')].some(input => input.value.trim().length > 0);
                let onError = block.querySelector('.invalid-feedback, .form-error-message');
                if (filled || identityFilled || onError) {
                    block.classList.remove('d-none');
                    block.removeAttribute('hidden');
                } else {
                    block.classList.add('d-none');
                    block.setAttribute('hidden', 'hidden');
                }
            }
            email.addEventListener('input', toggle);
            toggle();
        });
    }

    identityFields();

    /**
     * Widget Experience Hotel : ses champs sont alimentes, sans declencher sa soumission.
     * Une valeur posee en JS doit etre notifiee, sinon le widget la considere vide.
     */
    let fillWidgetInput = function (input, value) {
        if (!input) {
            return;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
    }

    /**
     * La reponse AJAX re-rend tout le conteneur, widget compris : un second bloc vide est
     * reinjecte (un <script> pose via innerHTML ne s'execute jamais). On ne garde que celui
     * qui porte reellement les champs, sinon les valeurs partent dans le doublon vide.
     */
    let widgetElement = function () {
        let widgets = [...document.querySelectorAll('.newsletter-widget')];
        let mounted = widgets.find(widget => widget.querySelector('input'));
        widgets.filter(widget => widget !== mounted).forEach(widget => widget.remove());

        return mounted;
    }

    let fillWidget = function (values) {
        let widget = widgetElement();
        if (!widget) {
            return;
        }
        // Les identifiants du widget sont suffixes par son numero (email-464, firstname-464...) :
        // on cible sur le fragment stable, jamais sur l'identifiant complet.
        fillWidgetInput(widget.querySelector('input[id*="email"]'), values.email);
        fillWidgetInput(widget.querySelector('input[id*="firstname"]'), values.firstname);
        fillWidgetInput(widget.querySelector('input[id*="lastname"]'), values.lastname);
    }

    /** Declenche la soumission du widget, une fois ses champs alimentes. */
    let submitWidget = function () {
        let widget = widgetElement();
        let submit = widget ? widget.querySelector('button[type="submit"], button.form-submit') : null;
        if (!submit) {
            return false;
        }
        submit.click();

        return true;
    }

    /** Loader du bouton : maintenu jusqu'a la redirection, rendu a l'internaute en cas d'erreur. */
    let toggleLoader = function (form, active) {
        if (!form) {
            return;
        }
        let spinner = form.querySelector('.newsletter-submit .spinner-border');
        let icon = form.querySelector('.newsletter-submit svg');
        if (spinner) {
            spinner.classList.toggle('d-none', !active);
        }
        if (icon) {
            icon.classList.toggle('d-none', active);
        }
    }

    /** Valeurs saisies, relevees avant le remplacement du conteneur par la reponse AJAX. */
    let formValues = function (form) {
        let identity = form.querySelectorAll('.newsletter-form-identity');
        let email = form.querySelector('.newsletter-form-email');
        return {
            email: email ? email.value.trim() : '',
            firstname: identity[0] ? identity[0].value.trim() : '',
            lastname: identity[1] ? identity[1].value.trim() : '',
        };
    }

    /** Events */
    let formsEvents = function () {
        document.querySelectorAll('.newsletter-form').forEach(function (form) {
            form.addEventListener('keydown', function (event) {
                if (event.key === "Enter") {
                    sendRequest(event, this);
                    return false;
                }
            });
        });
        document.querySelectorAll('.newsletter-submit').forEach(function (submit) {
            submit.onclick = function (event) {
                sendRequest(event, this.closest('form'));
            }
        });
    }

    formsEvents();

    function sendRequest(event, form) {

        event.preventDefault();

        import('../../../../vendor/components/recaptcha').then(({onSubmit: OnSubmit}) => {
            new OnSubmit(form);
        }).catch(error => console.error(error.message));

        let values = formValues(form);
        let icon = form.querySelector('.newsletter-submit svg');
        let iconSpinner = form.querySelector('.spinner-border');
        let containerId = form.closest('.newsletter-form-container').getAttribute('id');

        let beforeSend = function () {
            /** Remove errors */
            import('../../../../vendor/components/remove-errors').then(({default: removeErrors}) => {
                new removeErrors();
            }).catch(error => console.error(error.message));
            if (iconSpinner) {
                iconSpinner.classList.remove('d-none');
            }
            if (icon) {
                icon.classList.add('d-none');
            }
        }

        let xHttp = new XMLHttpRequest();
        xHttp.open("POST", form.getAttribute('action'), true);
        xHttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        beforeSend();
        xHttp.send(serialize(form));
        xHttp.onerror = function () {
            toggleLoader(form, false);
        }
        xHttp.onload = function () {
            if (this.status !== 200) {
                toggleLoader(form, false);
            }
            if (this.readyState === 4 && this.status === 200) {
                let response = JSON.parse(this.response)
                document.getElementById(containerId).outerHTML = response.html;
                widgetElement();
                formsEvents();
                import('../../../../vendor/components/keyup-fields').then(({default: keyupFields}) => {
                    new keyupFields();
                }).catch(error => console.error(error.message));
                identityFields();
                let renderedForm = document.querySelector('#' + containerId + ' .newsletter-form');
                if (response.success) {
                    // Uniquement si le formulaire est valide cote serveur : une saisie invalide
                    // (e-mail, prenom, nom ou consentement) ne part pas chez Experience Hotel.
                    fillWidget(values);
                    let widgetSent = submitWidget();
                    resetInputs();
                    if (response.redirection) {
                        // Le loader tourne jusqu'a la redirection, qui laisse d'abord a la requete
                        // du widget le temps de partir : naviguer tout de suite l'annulerait.
                        toggleLoader(renderedForm, true);
                        setTimeout(function () {
                            document.location.href = response.redirection;
                        }, widgetSent ? WIDGET_DELAY : 0);
                    } else {
                        toggleLoader(renderedForm, false);
                    }
                } else {
                    toggleLoader(renderedForm, false);
                }
            }
        }
    }

    /** Serialize form data */
    let serialize = function (form) {
        let serialized = []
        for (let i = 0; i < form.elements.length; i++) {
            let field = form.elements[i]
            if (!field.name || field.disabled || field.type === 'file' || field.type === 'reset' || field.type === 'submit' || field.type === 'button') continue
            if (field.type === 'select-multiple') {
                for (let n = 0; n < field.options.length; n++) {
                    if (!field.options[n].selected) continue
                    serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.options[n].value))
                }
            } else if ((field.type !== 'checkbox' && field.type !== 'radio') || field.checked) {
                serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.value))
            }
        }
        return serialized.join('&')
    }
}