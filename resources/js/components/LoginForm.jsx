import React, { useState } from 'react';
import { login } from './FunctionCalls';
// import { Alert } from 'reactstrap';



const LoginForm = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState({});

    const onChange = (e) => {
        const { name, value } = e.target;
        if (name === 'email') {
            setEmail(value);
        } else if (name === 'password') {
            setPassword(value);
        }
    };

    const onSubmit = (e) => {
        e.preventDefault();
        let errors = {};
        if (!email) errors.email = "Please enter user name";
        if (!password) errors.password = "Please enter password";

        if (Object.keys(errors).length > 0) {
            setErrors(errors);
            return;
        }

        const user = {
            email,
            password,
            errors: {}
        };

        login(user).then(res => {
           debugger; console.log(res)
            if (res.errorStatus) {
                if (res.data.not_found === "User not Verified") {
                    window.location = "/verify-code/" + btoa(email);
                } else {
                    setErrors(res.data);
                }
            } else {
                localStorage.setItem("section", btoa("inbox"));
                localStorage.setItem("value", btoa("1"));
                window.location = "/Home";
            }
        });
    };

    return (
        <div className="container">
            <div className="row">
                <div className="col-md-6 mt-5 mx-auto">
                    <form noValidate onSubmit={onSubmit}>
                        {errors && errors.not_found ? <Alert color="danger">
                            {errors.not_found}
                        </Alert> : ""}
                        <div className="form-group position-relative">
                            <span className={"input_bg_image span_email"}></span>
                            <input
                                type="email"
                                className="form-control"
                                name="email"
                                placeholder="Enter email test"
                                value={email}
                                onChange={onChange}
                                id="email"
                            />
                            {errors && errors.email ?
                                <label className="error" htmlFor="email">{errors.email}</label> : ""}
                        </div>
                        <div className="form-group position-relative">
                            <span className={"input_bg_image span_password"}></span>
                            <input
                                type="password"
                                className="form-control"
                                name="password"
                                placeholder="Password"
                                value={password}
                                onChange={onChange}
                                id="password"
                            />
                            {errors && errors.password ?
                                <label className="error" htmlFor="password">{errors.password}</label> : ""}
                        </div>
                        {/* OLD CODE */}
                        {/* <p className="d-block text-right mb-7"><a className="color-white" href={'/password-reset'}>Forgot
                        your password?</a></p> */}
                        <p className="d-block text-end mb-7"><a className="color-white" href={'/password-reset'}>Forgot
                            your password?</a></p>
                        {/* OLD CODE */}
                        {/* <button type="submit"
                            className="btn btn-lg btn-primary btn-block"
                        >
                            Sign in
                        </button> */}
                        <button
                            type="submit"
                            className="btn btn-lg btn-primary btn-block w-100"
                        >
                            Sign in
                        </button>
                    </form>
                    {/* <div className="row mt-3 justify-content-center">
                        <p className="float-left mr-2">Don't have an account ?</p>
                        <a className="color-white float-right" href={'/register'}>Sign Up</a>
                    </div> */}
                    <div className="row mt-3 justify-content-center">
                        <div className="d-flex justify-content-center align-items-center w-100">
                            <p className="mb-0 me-2">Don't have an account?</p>
                            <a className="color-white" href={'/register'}>Sign Up</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default LoginForm;