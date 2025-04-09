import React from "react";

const ProjectContext = React.createContext()

const ContextProvider = ProjectContext.Provider
const ContextConsumer = ProjectContext.Consumer

export {ContextProvider, ContextConsumer}
export default ProjectContext
