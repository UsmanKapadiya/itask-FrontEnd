import  React from "react"
import LogoImg from "../../../public/images/logo.png";

function Logo(){
    return(
            <div >

                    <img src={LogoImg} className="m-auto d-block"/>
                    <h1 className="text-center color-white">I-task</h1>
            </div>
    )
}

export default Logo
