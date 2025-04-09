import React, { useState } from 'react';
import { register } from './FunctionCalls';
import Submit from "../../../public/images/btn_next.png";
import addprofile from "../../../public/images/add_profile.png";
import editprofile from "../../../public/images/icon_camera.png";
import { message } from "antd";



const Register = () => {
    const [state, setState] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        file: '',
        previewURL: addprofile,
        errors: {},
        loading: ''
    });

    const onChange = (e) => {
        setState({ ...state, [e.target.name]: e.target.value });
    };

    const onSubmit = (e) => {
        e.preventDefault();
        let errors = {};
        if (!state.name) errors.name = "Please enter name";
        if (!state.email) errors.email = "Please enter unique email address";
        if (!state.password) errors.password = ["Please enter password"];
        if (state.password !== state.password_confirmation) errors.password_confirmation = "Passwords do not match";

        if (Object.keys(errors).length > 0) {
            setState({ ...state, errors });
            return;
        }
        const newUser = new FormData();
        newUser.append('name', state.name);
        newUser.append('email', state.email);
        newUser.append('password', state.password);
        newUser.append('password_confirmation', state.password_confirmation);
        if (state.file !== "") {
            newUser.append('avatar', state.file, state.file.name);
        }
        setState({ ...state, loading: "loading" });
        register(newUser).then(res => {
            if (res.errorStatus) {
                setState({ ...state, loading: "", errors: res.data });
            } else {
                window.location = "/verify-code/" + btoa(state.email);
            }
        });
    };

    const handleImageChange = (e) => {
        e.preventDefault();
        if (Math.round(e.target.files[0].size / 1000000) > 5) {
            message.error("Image size must be less than 5 MB");
        } else {
            let reader = new FileReader();
            let file = e.target.files[0];
            reader.onloadend = () => {
                setState({
                    ...state,
                    file: file,
                    previewURL: reader.result
                });
            };
            reader.readAsDataURL(file);
        }
    };

    const { previewURL, errors, loading } = state;

    return (
        <div className="bg-navyblue wrapper">
            <div className="container">
                <div className="row justify-content-center">

                    <div className="col-md-4">
                        <form noValidate onSubmit={onSubmit}>
                            <div className="previewComponent mt-5 mb-5">
                                <div className="profile_avtar fileInputContainer position-relative text-center mx-auto"

                                    style={{ backgroundImage: "url(" + previewURL + ")", width: 100, height: 100 }}>
                                    <input className="fileInput" accept="image/*"
                                        type="file"
                                        onChange={handleImageChange} />
                                    {previewURL.includes("add_", 1) ? "" :
                                        <img src={editprofile} className="accountediticon position-absolute" height="20" width="20" />
                                    }
                                </div>
                            </div>
                            <h1 className="h3 mb-3 font-weight-normal text-uppercase text-center mb-5">
                                <span className="title color-white">create account <hr className="centered-hr" /></span>
                            </h1>

                            <div className="form-group position-relative" id="register_container">
                                <span className={"input_bg_image span_name"}></span>
                                <input
                                    type="text"
                                    className="form-control"
                                    name="name"
                                    placeholder="Name"
                                    value={state.name}
                                    onChange={onChange}
                                    id="name"
                                />
                                {errors && errors.name ? <label className="error" htmlFor="name">{errors.name}</label> : ""}
                            </div>
                            <div className="form-group position-relative" id="register_container">
                                <span className={"input_bg_image span_email"}></span>
                                <input
                                    type="email"
                                    className="form-control"
                                    name="email"
                                    placeholder="Email"
                                    value={state.email}
                                    onChange={onChange}
                                    id="email"
                                />
                                {errors && errors.email ? <label className="error" htmlFor="email">{errors.email}</label> : ""}
                            </div>
                            <div className="form-group position-relative" id="register_container">
                                <span className={"input_bg_image span_password"}></span>
                                <input
                                    type="password"
                                    className="form-control"
                                    name="password"
                                    placeholder="Password"
                                    onChange={onChange}
                                    id="password"
                                />
                                {errors && errors.password ? <label className="error" htmlFor="password">{errors.password[0]}</label> : ""}
                            </div>
                            <div className="form-group position-relative" id="register_container">
                                <span className={"input_bg_image span_confirm-password"}></span>
                                <input
                                    type="password"
                                    className="form-control"
                                    name="password_confirmation"
                                    placeholder="Confirm Password"
                                    onChange={onChange}
                                    id="confirm-password"
                                />
                                {errors && errors.password ? <label className="error" htmlFor="password_confirmation">{errors.password[1]}</label> : ""}
                            </div>
                            <div className="text-center mt-5 mb-5" id="register_container">
                                {loading !== "" ? <div className="loader"></div> : <button type="submit" className="btn btn-primary">
                                    <img src={Submit} width="50" />
                                </button>}
                            </div>
                        </form>
                        <div id="error" className=""></div>
                    </div>
                </div>
            </div>
        </div>

    );
};

export default Register;
// if (document.getElementById('register_container')) {
//     ReactDOM.render(<Register />, document.getElementById('register_container'));
// }